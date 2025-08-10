<?php

/**
 * Swift ORM - Performance Optimization Examples
 *
 * This file demonstrates performance optimization techniques, best practices,
 * and common pitfalls to avoid when using Swift ORM.
 */

use CodeIgniter\Controller;

class PerformanceExamplesController extends Controller
{
    /*
    ===========================================
    N+1 QUERY PROBLEM DEMONSTRATIONS
    ===========================================
    */

    public function demonstrateN1Problem()
    {
        echo "<h2>N+1 Query Problem Demonstration</h2>";

        // Enable query logging
        $db = \Config\Database::connect();
        $queryCount = 0;

        // BAD: N+1 Query Problem
        echo "<h3>❌ BAD: Lazy Loading (N+1 Problem)</h3>";
        $startTime = microtime(true);
        $queryCount = $this->getQueryCount();

        $userModel = new \App\Models\UserModel();
        $users = $userModel->limit(10)->findAll(); // 1 query

        echo "<strong>Loaded " . count($users) . " users</strong><br>";

        foreach ($users as $user) {
            // Each of these property accesses triggers a separate query
            $posts = $user->posts;          // +1 query per user
            $profile = $user->profile;      // +1 query per user
            $comments = $user->comments;    // +1 query per user

            echo "User: {$user->name} | Posts: " . count($posts) .
                 " | Profile: " . ($profile ? 'Yes' : 'No') .
                 " | Comments: " . count($comments) . "<br>";
        }

        $badTime = microtime(true) - $startTime;
        $badQueries = $this->getQueryCount() - $queryCount;

        echo "<strong>Result: {$badQueries} queries in " . round($badTime, 4) . " seconds</strong><br><br>";

        // GOOD: Eager Loading
        echo "<h3>✅ GOOD: Eager Loading</h3>";
        $startTime = microtime(true);
        $queryCount = $this->getQueryCount();

        $users = $userModel
            ->with(['posts', 'profile', 'comments'])
            ->limit(10)
            ->findAll(); // Only 4 queries total (1 for users + 1 for each relationship)

        echo "<strong>Loaded " . count($users) . " users with relationships</strong><br>";

        foreach ($users as $user) {
            // No additional queries - data is already loaded
            echo "User: {$user->name} | Posts: " . count($user->posts) .
                 " | Profile: " . ($user->profile ? 'Yes' : 'No') .
                 " | Comments: " . count($user->comments) . "<br>";
        }

        $goodTime = microtime(true) - $startTime;
        $goodQueries = $this->getQueryCount() - $queryCount;

        echo "<strong>Result: {$goodQueries} queries in " . round($goodTime, 4) . " seconds</strong><br>";
        echo "<strong>Performance improvement: " . round(($badTime - $goodTime) / $badTime * 100, 1) . "%</strong><br>";
    }

    /*
    ===========================================
    SELECTIVE LOADING STRATEGIES
    ===========================================
    */

    public function selectiveLoading()
    {
        echo "<h2>Selective Loading Strategies</h2>";

        $postModel = new \App\Models\PostModel();

        // Load only required columns
        echo "<h3>Loading Only Required Columns</h3>";
        $startTime = microtime(true);

        $posts = $postModel
            ->select('id, title, user_id, created_at') // Only load needed columns
            ->with(['user' => function($query) {
                $query->select('id, name, email'); // Only load needed user columns
            }])
            ->where('published', 1)
            ->limit(20)
            ->findAll();

        $selectiveTime = microtime(true) - $startTime;
        echo "Loaded " . count($posts) . " posts with selective columns in " . round($selectiveTime, 4) . " seconds<br>";

        // Load all columns for comparison
        echo "<h3>Loading All Columns (for comparison)</h3>";
        $startTime = microtime(true);

        $postsAll = $postModel
            ->with(['user'])
            ->where('published', 1)
            ->limit(20)
            ->findAll();

        $fullTime = microtime(true) - $startTime;
        echo "Loaded " . count($postsAll) . " posts with all columns in " . round($fullTime, 4) . " seconds<br>";
        echo "Improvement: " . round(($fullTime - $selectiveTime) / $fullTime * 100, 1) . "%<br><br>";
    }

    /*
    ===========================================
    RELATIONSHIP COUNTING VS LOADING
    ===========================================
    */

    public function countingVsLoading()
    {
        echo "<h2>Relationship Counting vs Loading</h2>";

        $userModel = new \App\Models\UserModel();

        // When you only need counts
        echo "<h3>✅ GOOD: Using withCount() for counts only</h3>";
        $startTime = microtime(true);
        $queryCount = $this->getQueryCount();

        $users = $userModel
            ->withCount(['posts', 'comments'])
            ->limit(10)
            ->findAll();

        foreach ($users as $user) {
            echo "User: {$user->name} | Posts: {$user->posts_count} | Comments: {$user->comments_count}<br>";
        }

        $countTime = microtime(true) - $startTime;
        $countQueries = $this->getQueryCount() - $queryCount;
        echo "Using withCount(): {$countQueries} queries in " . round($countTime, 4) . " seconds<br><br>";

        // Loading full relationships just for counting
        echo "<h3>❌ BAD: Loading full relationships just for counting</h3>";
        $startTime = microtime(true);
        $queryCount = $this->getQueryCount();

        $users = $userModel
            ->with(['posts', 'comments'])
            ->limit(10)
            ->findAll();

        foreach ($users as $user) {
            echo "User: {$user->name} | Posts: " . count($user->posts) . " | Comments: " . count($user->comments) . "<br>";
        }

        $loadTime = microtime(true) - $startTime;
        $loadQueries = $this->getQueryCount() - $queryCount;
        echo "Loading full data: {$loadQueries} queries in " . round($loadTime, 4) . " seconds<br>";
        echo "Performance difference: " . round(($loadTime - $countTime) / $loadTime * 100, 1) . "% faster with withCount()<br><br>";
    }

    /*
    ===========================================
    PAGINATION PERFORMANCE
    ===========================================
    */

    public function paginationPerformance()
    {
        echo "<h2>Pagination Performance</h2>";

        $postModel = new \App\Models\PostModel();

        // Efficient pagination with relationships
        echo "<h3>Efficient Pagination</h3>";
        $startTime = microtime(true);

        $posts = $postModel
            ->select('posts.id, posts.title, posts.user_id, posts.created_at')
            ->with(['user' => function($query) {
                $query->select('id, name');
            }])
            ->withCount(['comments'])
            ->where('published', 1)
            ->orderBy('created_at', 'DESC')
            ->paginate(10);

        $paginationTime = microtime(true) - $startTime;
        $pager = $postModel->pager;

        echo "Page {$pager->getCurrentPage()} of {$pager->getPageCount()}<br>";
        echo "Loaded " . count($posts) . " posts in " . round($paginationTime, 4) . " seconds<br>";

        foreach ($posts as $post) {
            echo "- {$post->title} by {$post->user->name} ({$post->comments_count} comments)<br>";
        }
        echo "<br>";

        // Show pagination links
        echo "Pagination: " . $pager->links() . "<br><br>";
    }

    /*
    ===========================================
    CACHING STRATEGIES
    ===========================================
    */

    public function cachingStrategies()
    {
        echo "<h2>Caching Strategies</h2>";

        $cache = \Config\Services::cache();

        // Cache expensive query results
        echo "<h3>Query Result Caching</h3>";
        $cacheKey = 'popular_posts_last_week';

        $popularPosts = $cache->get($cacheKey);
        if ($popularPosts === null) {
            echo "Cache miss - loading from database...<br>";
            $startTime = microtime(true);

            $postModel = new \App\Models\PostModel();
            $popularPosts = $postModel
                ->select('posts.*, COUNT(comments.id) as comment_count')
                ->join('comments', 'comments.post_id = posts.id', 'left')
                ->where('posts.published', 1)
                ->where('posts.created_at >', date('Y-m-d', strtotime('-7 days')))
                ->groupBy('posts.id')
                ->orderBy('comment_count', 'DESC')
                ->with(['user'])
                ->limit(5)
                ->findAll();

            $queryTime = microtime(true) - $startTime;

            // Cache for 30 minutes
            $cache->save($cacheKey, $popularPosts, 1800);
            echo "Query executed in " . round($queryTime, 4) . " seconds<br>";
        } else {
            echo "Cache hit - data loaded from cache!<br>";
        }

        echo "Popular posts this week:<br>";
        foreach ($popularPosts as $post) {
            echo "- {$post->title} by {$post->user->name} ({$post->comment_count} comments)<br>";
        }
        echo "<br>";

        // Cache relationship counts
        echo "<h3>Relationship Count Caching</h3>";
        $userModel = new \App\Models\UserModel();
        $userId = 1;
        $countCacheKey = "user_stats_{$userId}";

        $userStats = $cache->get($countCacheKey);
        if ($userStats === null) {
            echo "Loading user statistics from database...<br>";

            $user = $userModel
                ->withCount(['posts', 'comments'])
                ->find($userId);

            $userStats = [
                'name' => $user->name,
                'posts_count' => $user->posts_count,
                'comments_count' => $user->comments_count,
                'cached_at' => date('Y-m-d H:i:s')
            ];

            // Cache for 1 hour
            $cache->save($countCacheKey, $userStats, 3600);
        } else {
            echo "User statistics loaded from cache (cached at: {$userStats['cached_at']})<br>";
        }

        echo "User: {$userStats['name']} | Posts: {$userStats['posts_count']} | Comments: {$userStats['comments_count']}<br><br>";
    }

    /*
    ===========================================
    MEMORY OPTIMIZATION
    ===========================================
    */

    public function memoryOptimization()
    {
        echo "<h2>Memory Optimization</h2>";

        $postModel = new \App\Models\PostModel();

        // Process large datasets in chunks
        echo "<h3>Processing Large Datasets in Chunks</h3>";
        $startMemory = memory_get_usage(true);
        $processed = 0;
        $chunkSize = 100;
        $totalPosts = $postModel->where('published', 1)->countAllResults(false);

        echo "Processing {$totalPosts} posts in chunks of {$chunkSize}...<br>";

        for ($offset = 0; $offset < $totalPosts; $offset += $chunkSize) {
            $posts = $postModel
                ->select('id, title, user_id')
                ->where('published', 1)
                ->limit($chunkSize, $offset)
                ->findAll();

            // Process each chunk
            foreach ($posts as $post) {
                // Simulate processing
                $processed++;
            }

            // Free memory
            unset($posts);

            if ($offset % 500 == 0) { // Log every 5 chunks
                $currentMemory = memory_get_usage(true);
                echo "Processed {$processed} posts | Memory: " . $this->formatBytes($currentMemory) . "<br>";
            }
        }

        $endMemory = memory_get_usage(true);
        echo "Total processed: {$processed} posts<br>";
        echo "Memory used: " . $this->formatBytes($endMemory - $startMemory) . "<br><br>";
    }

    /*
    ===========================================
    INDEX OPTIMIZATION RECOMMENDATIONS
    ===========================================
    */

    public function indexOptimizationRecommendations()
    {
        echo "<h2>Index Optimization Recommendations</h2>";

        $db = \Config\Database::connect();

        // Check for missing indexes on foreign keys
        echo "<h3>Foreign Key Index Analysis</h3>";

        $foreignKeys = [
            'posts.user_id',
            'comments.post_id',
            'comments.user_id',
            'comments.parent_id',
            'profiles.user_id',
            'user_roles.user_id',
            'user_roles.role_id',
            'post_tags.post_id',
            'post_tags.tag_id'
        ];

        foreach ($foreignKeys as $fk) {
            list($table, $column) = explode('.', $fk);

            // Check if index exists
            $indexes = $db->query("SHOW INDEX FROM {$table} WHERE Column_name = '{$column}'")->getResult();

            if (empty($indexes)) {
                echo "❌ Missing index on {$fk}<br>";
                echo "   Recommendation: CREATE INDEX idx_{$table}_{$column} ON {$table}({$column});<br>";
            } else {
                echo "✅ Index exists on {$fk}<br>";
            }
        }

        echo "<br>";

        // Composite index recommendations
        echo "<h3>Composite Index Recommendations</h3>";

        $compositeIndexes = [
            'posts' => ['published', 'created_at'],
            'comments' => ['post_id', 'created_at'],
            'posts' => ['user_id', 'published'],
        ];

        foreach ($compositeIndexes as $table => $columns) {
            $columnList = implode(', ', $columns);
            echo "Recommended composite index on {$table}: ({$columnList})<br>";
            echo "SQL: CREATE INDEX idx_{$table}_" . implode('_', $columns) . " ON {$table}({$columnList});<br><br>";
        }
    }

    /*
    ===========================================
    QUERY ANALYSIS TOOLS
    ===========================================
    */

    public function queryAnalysis()
    {
        echo "<h2>Query Analysis</h2>";

        $db = \Config\Database::connect();

        // Analyze slow queries
        echo "<h3>Query Performance Analysis</h3>";

        $queries = [
            "Find posts with comments" => "
                SELECT p.*, COUNT(c.id) as comment_count
                FROM posts p
                LEFT JOIN comments c ON c.post_id = p.id
                WHERE p.published = 1
                GROUP BY p.id
                ORDER BY comment_count DESC
                LIMIT 10
            ",
            "Find users with most posts" => "
                SELECT u.*, COUNT(p.id) as post_count
                FROM users u
                LEFT JOIN posts p ON p.user_id = u.id
                GROUP BY u.id
                ORDER BY post_count DESC
                LIMIT 10
            "
        ];

        foreach ($queries as $description => $sql) {
            echo "<h4>{$description}</h4>";

            $startTime = microtime(true);
            $result = $db->query($sql);
            $queryTime = microtime(true) - $startTime;

            echo "Query time: " . round($queryTime, 4) . " seconds<br>";
            echo "Rows returned: " . $result->getNumRows() . "<br>";

            // Show EXPLAIN for the query
            $explain = $db->query("EXPLAIN " . $sql)->getResult();
            echo "<details><summary>EXPLAIN output</summary>";
            echo "<table border='1'>";
            echo "<tr><th>Type</th><th>Possible Keys</th><th>Key</th><th>Rows</th><th>Extra</th></tr>";
            foreach ($explain as $row) {
                echo "<tr>";
                echo "<td>{$row->type}</td>";
                echo "<td>{$row->possible_keys}</td>";
                echo "<td>{$row->key}</td>";
                echo "<td>{$row->rows}</td>";
                echo "<td>{$row->Extra}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</details><br>";
        }
    }

    /*
    ===========================================
    BATCH OPERATION PERFORMANCE
    ===========================================
    */

    public function batchOperationPerformance()
    {
        echo "<h2>Batch Operation Performance</h2>";

        $userModel = new \App\Models\UserModel();
        $db = \Config\Database::connect();

        // Single inserts vs batch inserts
        echo "<h3>Single Insert vs Batch Insert Performance</h3>";

        $testData = [];
        for ($i = 1; $i <= 100; $i++) {
            $testData[] = [
                'name' => "Test User {$i}",
                'email' => "testuser{$i}@example.com",
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        // Single inserts (DON'T do this for many records)
        echo "<h4>❌ Single Inserts (100 records)</h4>";
        $startTime = microtime(true);
        $queryCount = $this->getQueryCount();

        foreach (array_slice($testData, 0, 10) as $data) { // Only test with 10 for demo
            $userModel->insert($data);
        }

        $singleTime = microtime(true) - $startTime;
        $singleQueries = $this->getQueryCount() - $queryCount;
        echo "10 single inserts: {$singleQueries} queries in " . round($singleTime, 4) . " seconds<br>";

        // Batch insert
        echo "<h4>✅ Batch Insert (100 records)</h4>";
        $startTime = microtime(true);
        $queryCount = $this->getQueryCount();

        $userModel->insertBatch($testData);

        $batchTime = microtime(true) - $startTime;
        $batchQueries = $this->getQueryCount() - $queryCount;
        echo "100 batch inserts: {$batchQueries} queries in " . round($batchTime, 4) . " seconds<br>";

        if ($singleTime > 0) {
            $improvement = round(($singleTime * 10 - $batchTime) / ($singleTime * 10) * 100, 1);
            echo "Estimated improvement for 100 records: {$improvement}%<br>";
        }

        // Clean up test data
        $db->query("DELETE FROM users WHERE email LIKE 'testuser%@example.com'");
        echo "<br>";
    }

    /*
    ===========================================
    HELPER METHODS
    ===========================================
    */

    private function getQueryCount(): int
    {
        // This is a simplified version - in reality you'd need to implement
        // proper query counting using CI4's query logging
        static $count = 0;
        return ++$count;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /*
    ===========================================
    PERFORMANCE MONITORING SETUP
    ===========================================
    */

    public function performanceMonitoringSetup()
    {
        echo "<h2>Performance Monitoring Setup</h2>";

        echo "<h3>1. Enable Debug Toolbar</h3>";
        echo "<p>Add to app/Config/Filters.php:</p>";
        echo "<pre>";
        echo "public \$globals = [\n";
        echo "    'before' => [\n";
        echo "        'toolbar'\n";
        echo "    ],\n";
        echo "];\n";
        echo "</pre>";

        echo "<h3>2. Database Query Logging</h3>";
        echo "<p>In your controller or model:</p>";
        echo "<pre>";
        echo "\$db = \\Config\\Database::connect();\n";
        echo "\$db->enableQueryLogging = true;\n";
        echo "// ... your queries ...\n";
        echo "\$queries = \$db->getQueries();\n";
        echo "foreach (\$queries as \$query) {\n";
        echo "    log_message('info', 'Query: ' . \$query['query'] . ' | Time: ' . \$query['time']);\n";
        echo "}\n";
        echo "</pre>";

        echo "<h3>3. Custom Performance Monitoring</h3>";
        echo "<p>Create a custom helper for monitoring:</p>";
        echo "<pre>";
        echo "class PerformanceMonitor {\n";
        echo "    private static \$startTime;\n";
        echo "    private static \$queryCount = 0;\n";
        echo "    \n";
        echo "    public static function start() {\n";
        echo "        self::\$startTime = microtime(true);\n";
        echo "        self::\$queryCount = 0;\n";
        echo "    }\n";
        echo "    \n";
        echo "    public static function end(\$operation = 'Operation') {\n";
        echo "        \$endTime = microtime(true);\n";
        echo "        \$duration = \$endTime - self::\$startTime;\n";
        echo "        log_message('info', \$operation . ' took ' . \$duration . ' seconds');\n";
        echo "        return \$duration;\n";
        echo "    }\n";
        echo "}\n";
        echo "</pre>";

        echo "<h3>4. Memory Usage Monitoring</h3>";
        echo "<pre>";
        echo "\$startMemory = memory_get_usage(true);\n";
        echo "// ... your code ...\n";
        echo "\$endMemory = memory_get_usage(true);\n";
        echo "\$memoryUsed = \$endMemory - \$startMemory;\n";
        echo "echo 'Memory used: ' . number_format(\$memoryUsed / 1024 / 1024, 2) . ' MB';\n";
        echo "</pre>";
    }
}

/*
===========================================
PERFORMANCE BEST PRACTICES SUMMARY
===========================================
*/

/*
QUERY OPTIMIZATION:
1. Always use eager loading when you know you'll need relationships
2. Use withCount() when you only need relationship counts
3. Load only required columns with select()
4. Use pagination for large datasets
5. Implement proper database indexing

MEMORY OPTIMIZATION:
1. Process large datasets in chunks
2. Unset variables when done with them
3. Use streaming for very large result sets
4. Monitor memory usage in production

CACHING STRATEGIES:
1. Cache expensive query results
2. Cache relationship counts that don't change often
3. Use appropriate cache TTL values
4. Clear cache when data changes

DATABASE DESIGN:
1. Index all foreign key columns
2. Create composite indexes for common query patterns
3. Normalize data appropriately
4. Consider denormalization for frequently accessed aggregates

MONITORING:
1. Enable debug toolbar in development
2. Log slow queries in production
3. Monitor memory usage
4. Set up performance alerts

COMMON ANTI-PATTERNS TO AVOID:
1. N+1 query problems (use eager loading)
2. Loading full relationships just for counts
3. Not using indexes on foreign keys
4. Processing large datasets without chunking
5. Not caching expensive operations
6. Using SELECT * when only specific columns are needed
*/
