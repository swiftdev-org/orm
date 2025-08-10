<?php

/**
 * Swift ORM - Advanced Query Examples
 *
 * This file demonstrates advanced querying techniques, complex relationships,
 * and sophisticated data retrieval patterns using Swift ORM.
 */

use CodeIgniter\Controller;

class AdvancedQueriesController extends Controller
{
    /*
    ===========================================
    COMPLEX EAGER LOADING PATTERNS
    ===========================================
    */

    public function complexEagerLoading()
    {
        $postModel = new \App\Models\PostModel();

        // Load posts with multiple nested relationships
        $posts = $postModel
            ->with([
                'user.profile',           // Author with profile
                'user.roles',             // Author's roles
                'comments.user.profile',  // Comments with author profiles
                'comments.replies.user',  // Comment replies with users
                'tags',                   // Post tags
                'categories.parent'       // Categories with parent categories
            ])
            ->where('published', 1)
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->findAll();

        foreach ($posts as $post) {
            echo "<div class='post'>";
            echo "<h2>{$post->title}</h2>";
            echo "<p>By: {$post->user->name}";

            if ($post->user->profile) {
                echo " - {$post->user->profile->bio}";
            }
            echo "</p>";

            // Display user roles
            if (!empty($post->user->roles)) {
                echo "<p>Roles: ";
                foreach ($post->user->roles as $role) {
                    echo "[{$role->name}] ";
                }
                echo "</p>";
            }

            // Display categories with hierarchy
            echo "<p>Categories: ";
            foreach ($post->categories as $category) {
                if ($category->parent) {
                    echo "{$category->parent->name} > ";
                }
                echo "{$category->name} ";
            }
            echo "</p>";

            // Display comments with nested replies
            echo "<div class='comments'>";
            foreach ($post->comments as $comment) {
                echo "<div class='comment'>";
                echo "<strong>{$comment->user->name}:</strong> {$comment->content}";

                if (!empty($comment->replies)) {
                    echo "<div class='replies'>";
                    foreach ($comment->replies as $reply) {
                        echo "<div class='reply'>";
                        echo "<strong>{$reply->user->name}:</strong> {$reply->content}";
                        echo "</div>";
                    }
                    echo "</div>";
                }
                echo "</div>";
            }
            echo "</div>";
            echo "</div><hr>";
        }
    }

    /*
    ===========================================
    CONDITIONAL RELATIONSHIP LOADING
    ===========================================
    */

    public function conditionalRelationshipLoading()
    {
        $userModel = new \App\Models\UserModel();

        // Load relationships based on user permissions or context
        $currentUserRole = 'admin'; // This would come from session/auth

        $with = ['profile']; // Always load profile

        // Conditionally add relationships based on permissions
        if ($currentUserRole === 'admin') {
            $with[] = 'roles';
            $with[] = 'posts.comments';
        } elseif ($currentUserRole === 'editor') {
            $with[] = 'posts';
        }

        $users = $userModel->with($with)->findAll();

        foreach ($users as $user) {
            echo "User: {$user->name}<br>";

            if ($user->relationLoaded('roles')) {
                echo "Roles: " . implode(', ', array_column($user->roles, 'name')) . "<br>";
            }

            if ($user->relationLoaded('posts')) {
                echo "Posts: " . count($user->posts) . "<br>";

                if ($currentUserRole === 'admin') {
                    $totalComments = 0;
                    foreach ($user->posts as $post) {
                        $totalComments += count($post->comments);
                    }
                    echo "Total comments on posts: {$totalComments}<br>";
                }
            }
            echo "<br>";
        }
    }

    /*
    ===========================================
    RELATIONSHIP COUNTING WITH CONDITIONS
    ===========================================
    */

    public function advancedRelationshipCounting()
    {
        $userModel = new \App\Models\UserModel();

        // Count relationships with conditions using subqueries
        $users = $userModel
            ->select('users.*')
            ->select('(SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id AND posts.published = 1) as published_posts_count')
            ->select('(SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id AND posts.published = 0) as draft_posts_count')
            ->select('(SELECT COUNT(*) FROM comments WHERE comments.user_id = users.id) as total_comments_count')
            ->select('(SELECT COUNT(*) FROM comments
                      WHERE comments.user_id = users.id
                      AND comments.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_comments_count')
            ->findAll();

        foreach ($users as $user) {
            echo "<div class='user-stats'>";
            echo "<h3>{$user->name}</h3>";
            echo "<ul>";
            echo "<li>Published Posts: {$user->published_posts_count}</li>";
            echo "<li>Draft Posts: {$user->draft_posts_count}</li>";
            echo "<li>Total Comments: {$user->total_comments_count}</li>";
            echo "<li>Recent Comments (30 days): {$user->recent_comments_count}</li>";
            echo "</ul>";
            echo "</div>";
        }
    }

    /*
    ===========================================
    COMPLEX WHERE CONDITIONS WITH RELATIONSHIPS
    ===========================================
    */

    public function complexWhereConditions()
    {
        $postModel = new \App\Models\PostModel();

        // Find posts by users in specific roles with certain criteria
        $posts = $postModel
            ->select('posts.*')
            ->join('users', 'users.id = posts.user_id')
            ->join('user_roles', 'user_roles.user_id = users.id')
            ->join('roles', 'roles.id = user_roles.role_id')
            ->where('posts.published', 1)
            ->where('roles.name', 'editor')
            ->where('posts.created_at >', date('Y-m-d', strtotime('-7 days')))
            ->groupBy('posts.id') // Avoid duplicates from joins
            ->with(['user', 'comments', 'tags'])
            ->orderBy('posts.created_at', 'DESC')
            ->findAll();

        echo "<h3>Recent posts by editors:</h3>";
        foreach ($posts as $post) {
            echo "<div>";
            echo "<h4>{$post->title}</h4>";
            echo "<p>By: {$post->user->name}</p>";
            echo "<p>Comments: " . count($post->comments) . "</p>";
            echo "<p>Tags: " . implode(', ', array_column($post->tags, 'name')) . "</p>";
            echo "</div><hr>";
        }
    }

    /*
    ===========================================
    RELATIONSHIP EXISTENCE QUERIES
    ===========================================
    */

    public function relationshipExistenceQueries()
    {
        $userModel = new \App\Models\UserModel();

        // Users who have published posts
        $usersWithPublishedPosts = $userModel
            ->whereIn('id', function($builder) {
                return $builder->select('user_id')
                              ->from('posts')
                              ->where('published', 1);
            })
            ->with(['posts' => function($query) {
                $query->where('published', 1);
            }])
            ->findAll();

        echo "<h3>Users with published posts:</h3>";
        foreach ($usersWithPublishedPosts as $user) {
            echo "{$user->name} - {count($user->posts)} published posts<br>";
        }

        // Users who have never posted
        $usersWithoutPosts = $userModel
            ->whereNotIn('id', function($builder) {
                return $builder->select('user_id')->from('posts');
            })
            ->findAll();

        echo "<h3>Users who have never posted:</h3>";
        foreach ($usersWithoutPosts as $user) {
            echo "{$user->name}<br>";
        }

        // Users who have posts but no comments
        $usersWithPostsButNoComments = $userModel
            ->whereIn('id', function($builder) {
                return $builder->select('user_id')->from('posts');
            })
            ->whereNotIn('id', function($builder) {
                return $builder->select('user_id')->from('comments');
            })
            ->with(['posts'])
            ->findAll();

        echo "<h3>Users with posts but no comments:</h3>";
        foreach ($usersWithPostsButNoComments as $user) {
            echo "{$user->name} - {count($user->posts)} posts<br>";
        }
    }

    /*
    ===========================================
    AGGREGATION QUERIES
    ===========================================
    */

    public function aggregationQueries()
    {
        $db = \Config\Database::connect();

        // Most active users by post count
        $mostActiveUsers = $db->query("
            SELECT u.*, COUNT(p.id) as post_count
            FROM users u
            LEFT JOIN posts p ON p.user_id = u.id
            GROUP BY u.id
            ORDER BY post_count DESC
            LIMIT 10
        ")->getResult();

        echo "<h3>Most Active Users by Post Count:</h3>";
        foreach ($mostActiveUsers as $user) {
            echo "{$user->name} - {$user->post_count} posts<br>";
        }

        // Posts with most comments
        $postsWithMostComments = $db->query("
            SELECT p.*, u.name as author_name, COUNT(c.id) as comment_count
            FROM posts p
            LEFT JOIN users u ON u.id = p.user_id
            LEFT JOIN comments c ON c.post_id = p.id
            WHERE p.published = 1
            GROUP BY p.id
            ORDER BY comment_count DESC
            LIMIT 10
        ")->getResult();

        echo "<h3>Posts with Most Comments:</h3>";
        foreach ($postsWithMostComments as $post) {
            echo "{$post->title} by {$post->author_name} - {$post->comment_count} comments<br>";
        }

        // Average posts per user by role
        $avgPostsByRole = $db->query("
            SELECT r.name as role_name,
                   COUNT(DISTINCT u.id) as user_count,
                   COUNT(p.id) as total_posts,
                   ROUND(COUNT(p.id) / COUNT(DISTINCT u.id), 2) as avg_posts_per_user
            FROM roles r
            LEFT JOIN user_roles ur ON ur.role_id = r.id
            LEFT JOIN users u ON u.id = ur.user_id
            LEFT JOIN posts p ON p.user_id = u.id
            GROUP BY r.id, r.name
            ORDER BY avg_posts_per_user DESC
        ")->getResult();

        echo "<h3>Average Posts per User by Role:</h3>";
        foreach ($avgPostsByRole as $stat) {
            echo "{$stat->role_name}: {$stat->avg_posts_per_user} posts/user ({$stat->user_count} users, {$stat->total_posts} total posts)<br>";
        }
    }

    /*
    ===========================================
    PAGINATION WITH RELATIONSHIPS
    ===========================================
    */

    public function paginationWithRelationships()
    {
        $postModel = new \App\Models\PostModel();

        // Paginate posts with relationships
        $posts = $postModel
            ->with(['user', 'tags', 'comments.user'])
            ->withCount(['comments'])
            ->where('published', 1)
            ->orderBy('created_at', 'DESC')
            ->paginate(5);

        $pager = $postModel->pager;

        echo "<h3>Posts (Page {$pager->getCurrentPage()} of {$pager->getPageCount()})</h3>";

        foreach ($posts as $post) {
            echo "<div class='post'>";
            echo "<h4>{$post->title}</h4>";
            echo "<p>By: {$post->user->name}</p>";
            echo "<p>Comments: {$post->comments_count}</p>";
            echo "<p>Tags: " . implode(', ', array_column($post->tags, 'name')) . "</p>";

            // Show first few comments
            if (!empty($post->comments)) {
                echo "<div class='recent-comments'>";
                echo "<strong>Recent Comments:</strong><br>";
                $commentCount = 0;
                foreach ($post->comments as $comment) {
                    if ($commentCount >= 3) break;
                    echo "- {$comment->user->name}: " . substr($comment->content, 0, 50) . "...<br>";
                    $commentCount++;
                }
                if (count($post->comments) > 3) {
                    echo "... and " . (count($post->comments) - 3) . " more<br>";
                }
                echo "</div>";
            }
            echo "</div><hr>";
        }

        // Pagination links
        echo $pager->links();
    }

    /*
    ===========================================
    SEARCH WITH RELATIONSHIPS
    ===========================================
    */

    public function searchWithRelationships()
    {
        $searchTerm = $_GET['search'] ?? 'laravel';
        $postModel = new \App\Models\PostModel();

        // Search posts by title, content, author name, or tags
        $posts = $postModel
            ->select('posts.*')
            ->join('users', 'users.id = posts.user_id')
            ->join('post_tags pt', 'pt.post_id = posts.id', 'left')
            ->join('tags', 'tags.id = pt.tag_id', 'left')
            ->groupStart()
                ->like('posts.title', $searchTerm)
                ->orLike('posts.content', $searchTerm)
                ->orLike('users.name', $searchTerm)
                ->orLike('tags.name', $searchTerm)
            ->groupEnd()
            ->where('posts.published', 1)
            ->groupBy('posts.id')
            ->with(['user', 'tags', 'comments.user'])
            ->withCount(['comments'])
            ->orderBy('posts.created_at', 'DESC')
            ->findAll();

        echo "<h3>Search Results for: '{$searchTerm}'</h3>";
        echo "<p>Found " . count($posts) . " posts</p>";

        foreach ($posts as $post) {
            echo "<div class='search-result'>";
            echo "<h4>{$post->title}</h4>";
            echo "<p>By: {$post->user->name}</p>";
            echo "<p>" . substr($post->content, 0, 200) . "...</p>";
            echo "<p>Tags: " . implode(', ', array_column($post->tags, 'name')) . "</p>";
            echo "<p>Comments: {$post->comments_count}</p>";
            echo "</div><hr>";
        }
    }

    /*
    ===========================================
    REPORTING QUERIES
    ===========================================
    */

    public function reportingQueries()
    {
        $db = \Config\Database::connect();

        // Monthly post statistics
        $monthlyStats = $db->query("
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_posts,
                SUM(CASE WHEN published = 1 THEN 1 ELSE 0 END) as published_posts,
                COUNT(DISTINCT user_id) as active_authors
            FROM posts
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ")->getResult();

        echo "<h3>Monthly Statistics (Last 12 Months):</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Month</th><th>Total Posts</th><th>Published</th><th>Active Authors</th></tr>";
        foreach ($monthlyStats as $stat) {
            echo "<tr>";
            echo "<td>{$stat->month}</td>";
            echo "<td>{$stat->total_posts}</td>";
            echo "<td>{$stat->published_posts}</td>";
            echo "<td>{$stat->active_authors}</td>";
            echo "</tr>";
        }
        echo "</table>";

        // User engagement report
        $userEngagement = $db->query("
            SELECT
                u.name,
                u.email,
                COUNT(DISTINCT p.id) as posts_count,
                COUNT(DISTINCT c.id) as comments_count,
                MAX(p.created_at) as last_post_date,
                MAX(c.created_at) as last_comment_date,
                DATEDIFF(NOW(), GREATEST(IFNULL(MAX(p.created_at), '1970-01-01'),
                                       IFNULL(MAX(c.created_at), '1970-01-01'))) as days_since_activity
            FROM users u
            LEFT JOIN posts p ON p.user_id = u.id
            LEFT JOIN comments c ON c.user_id = u.id
            GROUP BY u.id
            HAVING posts_count > 0 OR comments_count > 0
            ORDER BY days_since_activity ASC
        ")->getResult();

        echo "<h3>User Engagement Report:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>User</th><th>Posts</th><th>Comments</th><th>Last Activity</th><th>Days Since Activity</th></tr>";
        foreach ($userEngagement as $user) {
            $lastActivity = max($user->last_post_date, $user->last_comment_date);
            echo "<tr>";
            echo "<td>{$user->name}</td>";
            echo "<td>{$user->posts_count}</td>";
            echo "<td>{$user->comments_count}</td>";
            echo "<td>{$lastActivity}</td>";
            echo "<td>{$user->days_since_activity}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    /*
    ===========================================
    CACHING STRATEGIES
    ===========================================
    */

    public function cachingStrategies()
    {
        $cache = \Config\Services::cache();
        $cacheKey = 'popular_posts_with_authors';

        // Try to get from cache first
        $popularPosts = $cache->get($cacheKey);

        if ($popularPosts === null) {
            echo "Loading from database...<br>";

            $postModel = new \App\Models\PostModel();

            // Complex query for popular posts
            $popularPosts = $postModel
                ->select('posts.*, COUNT(comments.id) as comment_count')
                ->join('comments', 'comments.post_id = posts.id', 'left')
                ->where('posts.published', 1)
                ->where('posts.created_at >', date('Y-m-d', strtotime('-30 days')))
                ->groupBy('posts.id')
                ->having('comment_count >=', 5)
                ->orderBy('comment_count', 'DESC')
                ->with(['user', 'tags'])
                ->limit(10)
                ->findAll();

            // Cache for 1 hour
            $cache->save($cacheKey, $popularPosts, 3600);
        } else {
            echo "Loaded from cache!<br>";
        }

        echo "<h3>Popular Posts (30 days, 5+ comments):</h3>";
        foreach ($popularPosts as $post) {
            echo "<div>";
            echo "<h4>{$post->title}</h4>";
            echo "<p>By: {$post->user->name}</p>";
            echo "<p>Comments: {$post->comment_count}</p>";
            echo "<p>Tags: " . implode(', ', array_column($post->tags, 'name')) . "</p>";
            echo "</div><hr>";
        }
    }

    /*
    ===========================================
    BULK OPERATIONS WITH RELATIONSHIPS
    ===========================================
    */

    public function bulkOperationsWithRelationships()
    {
        $db = \Config\Database::connect();

        // Bulk update posts by specific authors
        $authorIds = [1, 2, 3];
        $postModel = new \App\Models\PostModel();

        // Update all posts by specific authors
        $affectedRows = $postModel
            ->whereIn('user_id', $authorIds)
            ->set(['published' => 1, 'updated_at' => date('Y-m-d H:i:s')])
            ->update();

        echo "Updated {$affectedRows} posts<br>";

        // Bulk delete old posts with no comments
        $oldPostsWithNoComments = $postModel
            ->select('posts.id')
            ->join('comments', 'comments.post_id = posts.id', 'left')
            ->where('posts.created_at <', date('Y-m-d', strtotime('-1 year')))
            ->where('comments.id IS NULL', null, false)
            ->findColumn('id');

        if (!empty($oldPostsWithNoComments)) {
            $deletedCount = $postModel->delete($oldPostsWithNoComments);
            echo "Deleted {$deletedCount} old posts with no comments<br>";
        }

        // Bulk create relationships (assign tags to posts)
        $postIds = [1, 2, 3];
        $tagIds = [1, 2];

        $pivotData = [];
        foreach ($postIds as $postId) {
            foreach ($tagIds as $tagId) {
                $pivotData[] = [
                    'post_id' => $postId,
                    'tag_id' => $tagId,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }

        $db->table('post_tags')->insertBatch($pivotData);
        echo "Created " . count($pivotData) . " post-tag relationships<br>";
    }
}
