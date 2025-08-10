<?php

/**
 * Swift ORM - Relationship Examples
 *
 * This file demonstrates all types of relationships and how to work with them
 * in Swift ORM for CodeIgniter 4.
 */

use Swift\ORM\Entity;
use Swift\ORM\Model;

/*
===========================================
ENTITY DEFINITIONS WITH RELATIONSHIPS
===========================================
*/

class User extends Entity
{
    protected $attributes = [
        'id' => null,
        'name' => null,
        'email' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected $casts = [
        'id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * One-to-One: User has one profile
     */
    public function profile()
    {
        return $this->hasOne('App\Entities\Profile', 'user_id', 'id');
    }

    /**
     * One-to-Many: User has many posts
     */
    public function posts()
    {
        return $this->hasMany('App\Entities\Post', 'user_id', 'id');
    }

    /**
     * One-to-Many: User has many comments
     */
    public function comments()
    {
        return $this->hasMany('App\Entities\Comment', 'user_id', 'id');
    }

    /**
     * Many-to-Many: User has many roles
     */
    public function roles()
    {
        return $this->belongsToMany(
            'App\Entities\Role',
            'user_roles',      // pivot table
            'user_id',         // foreign key
            'role_id',         // related key
            'id',              // local key
            'id'               // related local key
        );
    }

    /**
     * Many-to-Many: User has many skills
     */
    public function skills()
    {
        return $this->belongsToMany('App\Entities\Skill', 'user_skills');
    }
}

class Post extends Entity
{
    protected $attributes = [
        'id' => null,
        'title' => null,
        'content' => null,
        'user_id' => null,
        'published' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'published' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Many-to-One: Post belongs to user
     */
    public function user()
    {
        return $this->belongsTo('App\Entities\User', 'user_id', 'id');
    }

    /**
     * Alias for user relationship
     */
    public function author()
    {
        return $this->user();
    }

    /**
     * One-to-Many: Post has many comments
     */
    public function comments()
    {
        return $this->hasMany('App\Entities\Comment', 'post_id', 'id');
    }

    /**
     * Many-to-Many: Post has many tags
     */
    public function tags()
    {
        return $this->belongsToMany('App\Entities\Tag', 'post_tags');
    }

    /**
     * Many-to-Many: Post has many categories
     */
    public function categories()
    {
        return $this->belongsToMany('App\Entities\Category', 'post_categories');
    }
}

class Comment extends Entity
{
    protected $attributes = [
        'id' => null,
        'content' => null,
        'post_id' => null,
        'user_id' => null,
        'parent_id' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected $casts = [
        'id' => 'integer',
        'post_id' => 'integer',
        'user_id' => 'integer',
        'parent_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Many-to-One: Comment belongs to post
     */
    public function post()
    {
        return $this->belongsTo('App\Entities\Post', 'post_id', 'id');
    }

    /**
     * Many-to-One: Comment belongs to user
     */
    public function user()
    {
        return $this->belongsTo('App\Entities\User', 'user_id', 'id');
    }

    /**
     * Self-referencing: Comment has many replies
     */
    public function replies()
    {
        return $this->hasMany('App\Entities\Comment', 'parent_id', 'id');
    }

    /**
     * Self-referencing: Comment belongs to parent comment
     */
    public function parent()
    {
        return $this->belongsTo('App\Entities\Comment', 'parent_id', 'id');
    }
}

class Profile extends Entity
{
    protected $attributes = [
        'id' => null,
        'user_id' => null,
        'bio' => null,
        'avatar' => null,
        'website' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * One-to-One: Profile belongs to user
     */
    public function user()
    {
        return $this->belongsTo('App\Entities\User', 'user_id', 'id');
    }
}

/*
===========================================
RELATIONSHIP USAGE EXAMPLES
===========================================
*/

use CodeIgniter\Controller;

class RelationshipExampleController extends Controller
{
    /*
    ===========================================
    LAZY LOADING EXAMPLES
    ===========================================
    */

    public function lazyLoadingExample()
    {
        $userModel = new \App\Models\UserModel();

        // Get user without relationships
        $user = $userModel->find(1);

        if ($user) {
            echo "User: " . $user->name . "<br>";

            // Lazy load profile (triggers a query when accessed)
            if ($user->profile) {
                echo "Bio: " . $user->profile->bio . "<br>";
            }

            // Lazy load posts (triggers another query)
            echo "Posts by " . $user->name . ":<br>";
            foreach ($user->posts as $post) {
                echo "- " . $post->title . "<br>";
            }

            // Lazy load comments
            echo "Comments by " . $user->name . ":<br>";
            foreach ($user->comments as $comment) {
                echo "- " . substr($comment->content, 0, 50) . "...<br>";
            }
        }
    }

    /*
    ===========================================
    EAGER LOADING EXAMPLES
    ===========================================
    */

    public function eagerLoadingBasic()
    {
        $userModel = new \App\Models\UserModel();

        // Load users with their posts (only 2 queries total)
        $users = $userModel->with(['posts'])->findAll();

        foreach ($users as $user) {
            echo "User: " . $user->name . "<br>";
            echo "Posts: " . count($user->posts) . "<br><br>";
        }
    }

    public function eagerLoadingMultiple()
    {
        $userModel = new \App\Models\UserModel();

        // Load users with multiple relationships
        $users = $userModel->with(['posts', 'profile', 'comments'])->findAll();

        foreach ($users as $user) {
            echo "User: " . $user->name . "<br>";
            echo "Bio: " . ($user->profile->bio ?? 'No bio') . "<br>";
            echo "Posts: " . count($user->posts) . "<br>";
            echo "Comments: " . count($user->comments) . "<br><br>";
        }
    }

    public function eagerLoadingNested()
    {
        $postModel = new \App\Models\PostModel();

        // Load posts with user's profile and comment authors
        $posts = $postModel->with([
            'user.profile',      // Load post author with their profile
            'comments.user',     // Load comments with their authors
            'tags',              // Load post tags
            'categories'         // Load post categories
        ])->findAll();

        foreach ($posts as $post) {
            echo "Post: " . $post->title . "<br>";
            echo "Author: " . $post->user->name . "<br>";
            echo "Author Bio: " . ($post->user->profile->bio ?? 'No bio') . "<br>";

            echo "Tags: ";
            foreach ($post->tags as $tag) {
                echo $tag->name . " ";
            }
            echo "<br>";

            echo "Comments:<br>";
            foreach ($post->comments as $comment) {
                echo "- " . $comment->user->name . ": " . substr($comment->content, 0, 30) . "...<br>";
            }
            echo "<br>";
        }
    }

    /*
    ===========================================
    RELATIONSHIP COUNTING
    ===========================================
    */

    public function relationshipCounting()
    {
        $userModel = new \App\Models\UserModel();

        // Count relationships without loading them
        $users = $userModel->withCount(['posts', 'comments'])->findAll();

        foreach ($users as $user) {
            echo $user->name . ":<br>";
            echo "- Posts: " . $user->posts_count . "<br>";
            echo "- Comments: " . $user->comments_count . "<br><br>";
        }
    }

    public function combinedLoadingAndCounting()
    {
        $userModel = new \App\Models\UserModel();

        // Load some relationships and count others
        $users = $userModel
            ->with(['profile'])           // Load profile data
            ->withCount(['posts', 'comments'])  // Just count posts and comments
            ->findAll();

        foreach ($users as $user) {
            echo $user->name . ":<br>";
            echo "- Bio: " . ($user->profile->bio ?? 'No bio') . "<br>";
            echo "- Posts count: " . $user->posts_count . "<br>";
            echo "- Comments count: " . $user->comments_count . "<br><br>";
        }
    }

    /*
    ===========================================
    MANY-TO-MANY RELATIONSHIPS
    ===========================================
    */

    public function manyToManyExample()
    {
        $userModel = new \App\Models\UserModel();

        // Load users with their roles
        $users = $userModel->with(['roles'])->findAll();

        foreach ($users as $user) {
            echo "User: " . $user->name . "<br>";
            echo "Roles: ";

            foreach ($user->roles as $role) {
                echo $role->name . " ";
            }
            echo "<br><br>";
        }
    }

    public function postTagsExample()
    {
        $postModel = new \App\Models\PostModel();

        // Load posts with their tags and categories
        $posts = $postModel->with(['tags', 'categories'])->findAll();

        foreach ($posts as $post) {
            echo "Post: " . $post->title . "<br>";

            echo "Tags: ";
            foreach ($post->tags as $tag) {
                echo "[" . $tag->name . "] ";
            }
            echo "<br>";

            echo "Categories: ";
            foreach ($post->categories as $category) {
                echo "[" . $category->name . "] ";
            }
            echo "<br><br>";
        }
    }

    /*
    ===========================================
    SELF-REFERENCING RELATIONSHIPS
    ===========================================
    */

    public function selfReferencingExample()
    {
        $commentModel = new \App\Models\CommentModel();

        // Load top-level comments with their replies
        $comments = $commentModel
            ->with(['replies.user', 'user'])
            ->where('parent_id', null)  // Top-level comments only
            ->findAll();

        foreach ($comments as $comment) {
            echo "Comment by " . $comment->user->name . ":<br>";
            echo $comment->content . "<br>";

            if (!empty($comment->replies)) {
                echo "Replies:<br>";
                foreach ($comment->replies as $reply) {
                    echo "  └─ " . $reply->user->name . ": " . $reply->content . "<br>";
                }
            }
            echo "<br>";
        }
    }

    /*
    ===========================================
    CONDITIONAL RELATIONSHIP LOADING
    ===========================================
    */

    public function conditionalLoading()
    {
        $userModel = new \App\Models\UserModel();

        // Load different relationships based on conditions
        $includeProfile = true;
        $includePosts = false;

        $with = [];

        if ($includeProfile) {
            $with[] = 'profile';
        }

        if ($includePosts) {
            $with[] = 'posts';
        }

        $users = $userModel->with($with)->findAll();

        foreach ($users as $user) {
            echo "User: " . $user->name . "<br>";

            if ($user->relationLoaded('profile')) {
                echo "Bio: " . ($user->profile->bio ?? 'No bio') . "<br>";
            }

            if ($user->relationLoaded('posts')) {
                echo "Posts: " . count($user->posts) . "<br>";
            }

            echo "<br>";
        }
    }

    /*
    ===========================================
    QUERYING RELATIONSHIPS
    ===========================================
    */

    public function queryingRelationships()
    {
        $userModel = new \App\Models\UserModel();

        // Get users who have posts
        $usersWithPosts = $userModel
            ->whereIn('id', function($builder) {
                return $builder->select('user_id')
                              ->from('posts')
                              ->distinct();
            })
            ->with(['posts'])
            ->findAll();

        foreach ($usersWithPosts as $user) {
            echo $user->name . " has " . count($user->posts) . " posts<br>";
        }
    }

    public function relationshipConstraints()
    {
        $postModel = new \App\Models\PostModel();

        // Load posts with only published status and recent comments
        $posts = $postModel
            ->where('published', 1)
            ->with(['user', 'comments' => function($query) {
                $query->where('created_at >', date('Y-m-d H:i:s', strtotime('-30 days')));
            }])
            ->findAll();

        foreach ($posts as $post) {
            echo "Post: " . $post->title . " by " . $post->user->name . "<br>";
            echo "Recent comments: " . count($post->comments) . "<br><br>";
        }
    }

    /*
    ===========================================
    WORKING WITH PIVOT DATA
    ===========================================
    */

    public function pivotDataExample()
    {
        // Note: This is conceptual - actual pivot data access would need
        // additional implementation in the BelongsToMany relation class

        $userModel = new \App\Models\UserModel();

        $users = $userModel->with(['roles'])->findAll();

        foreach ($users as $user) {
            echo "User: " . $user->name . "<br>";
            echo "Roles:<br>";

            foreach ($user->roles as $role) {
                echo "- " . $role->name;
                // Access pivot data (if implemented)
                // echo " (assigned: " . $role->pivot->created_at . ")";
                echo "<br>";
            }
            echo "<br>";
        }
    }

    /*
    ===========================================
    PERFORMANCE COMPARISON
    ===========================================
    */

    public function performanceComparison()
    {
        $userModel = new \App\Models\UserModel();

        echo "<h3>Performance Comparison</h3>";

        // BAD: N+1 Query Problem
        $start = microtime(true);
        $users = $userModel->findAll();

        $queryCount = 1; // Initial query
        foreach ($users as $user) {
            $posts = $user->posts; // Each access triggers a new query
            $queryCount++;
        }
        $badTime = microtime(true) - $start;

        echo "BAD (Lazy Loading): {$queryCount} queries in " . round($badTime, 4) . " seconds<br>";

        // GOOD: Eager Loading
        $start = microtime(true);
        $users = $userModel->with(['posts'])->findAll();

        foreach ($users as $user) {
            $posts = $user->posts; // No additional queries
        }
        $goodTime = microtime(true) - $start;

        echo "GOOD (Eager Loading): 2 queries in " . round($goodTime, 4) . " seconds<br>";
        echo "Performance improvement: " . round(($badTime - $goodTime) / $badTime * 100, 2) . "%<br>";
    }

    /*
    ===========================================
    RELATIONSHIP EXISTENCE QUERIES
    ===========================================
    */

    public function relationshipExistence()
    {
        $userModel = new \App\Models\UserModel();

        // Get users who have at least one post
        $usersWithPosts = $userModel
            ->whereIn('id', function($builder) {
                return $builder->select('user_id')->from('posts');
            })
            ->findAll();

        echo "Users with posts: " . count($usersWithPosts) . "<br>";

        // Get users who don't have any posts
        $usersWithoutPosts = $userModel
            ->whereNotIn('id', function($builder) {
                return $builder->select('user_id')->from('posts');
            })
            ->findAll();

        echo "Users without posts: " . count($usersWithoutPosts) . "<br>";
    }

    /*
    ===========================================
    RELATIONSHIP AGGREGATES
    ===========================================
    */

    public function relationshipAggregates()
    {
        $userModel = new \App\Models\UserModel();

        // Get users with post counts using subquery
        $users = $userModel
            ->select('users.*, (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id) as posts_count')
            ->findAll();

        foreach ($users as $user) {
            echo $user->name . " has " . $user->posts_count . " posts<br>";
        }

        // Or using withCount (simpler)
        $users = $userModel->withCount(['posts'])->findAll();

        foreach ($users as $user) {
            echo $user->name . " has " . $user->posts_count . " posts<br>";
        }
    }

    /*
    ===========================================
    DYNAMIC RELATIONSHIP LOADING
    ===========================================
    */

    public function dynamicRelationshipLoading()
    {
        $userModel = new \App\Models\UserModel();

        // Start with basic user query
        $query = $userModel->where('active', 1);

        // Dynamically add relationships based on parameters
        $loadProfile = $_GET['profile'] ?? false;
        $loadPosts = $_GET['posts'] ?? false;
        $loadComments = $_GET['comments'] ?? false;

        $with = [];
        if ($loadProfile) $with[] = 'profile';
        if ($loadPosts) $with[] = 'posts';
        if ($loadComments) $with[] = 'comments';

        if (!empty($with)) {
            $query = $query->with($with);
        }

        $users = $query->findAll();

        foreach ($users as $user) {
            echo "User: " . $user->name . "<br>";

            if ($user->relationLoaded('profile')) {
                echo "- Profile loaded<br>";
            }

            if ($user->relationLoaded('posts')) {
                echo "- Posts: " . count($user->posts) . "<br>";
            }

            if ($user->relationLoaded('comments')) {
                echo "- Comments: " . count($user->comments) . "<br>";
            }

            echo "<br>";
        }
    }
}

/*
===========================================
RELATIONSHIP BEST PRACTICES
===========================================
*/

/*
1. EAGER LOADING GUIDELINES:
   - Use eager loading when you know you'll access the relationships
   - Load only the relationships you need
   - Be careful with deep nesting as it can cause performance issues
   - Use withCount() when you only need counts, not the actual data

2. LAZY LOADING GUIDELINES:
   - Use sparingly to avoid N+1 problems
   - Good for optional relationships that may not always be needed
   - Monitor query counts during development

3. MANY-TO-MANY TIPS:
   - Ensure pivot tables follow naming conventions (alphabetical order)
   - Consider adding timestamps to pivot tables for audit trails
   - Use intermediate models for complex pivot relationships

4. SELF-REFERENCING RELATIONSHIPS:
   - Be careful with infinite loops
   - Consider using recursive CTEs for deep hierarchies
   - Limit depth when loading nested self-references

5. PERFORMANCE OPTIMIZATION:
   - Use select() to limit columns when not all are needed
   - Index foreign key columns
   - Consider denormalization for frequently accessed counts
   - Use database-level aggregations when possible

6. RELATIONSHIP NAMING:
   - Use descriptive names that clearly indicate the relationship
   - Follow consistent naming conventions across your application
   - Consider creating alias methods for different contexts (e.g., author() for user())

7. ERROR HANDLING:
   - Always check if relationships are loaded before accessing
   - Handle cases where related records might not exist
   - Use null coalescing operators for optional relationships

8. TESTING RELATIONSHIPS:
   - Write tests that verify relationship definitions
   - Test both eager and lazy loading scenarios
   - Verify that foreign key constraints work as expected
*/

/*
===========================================
ADVANCED RELATIONSHIP PATTERNS
===========================================
*/

// Polymorphic relationships (would require additional implementation)
class Like extends Entity
{
    // This would be for polymorphic relationships
    // public function likeable()
    // {
    //     return $this->morphTo('likeable');
    // }
}

// Has-many-through relationships (would require additional implementation)
class Country extends Entity
{
    // public function posts()
    // {
    //     return $this->hasManyThrough('App\Entities\Post', 'App\Entities\User');
    // }
}

// Conditional relationships
class User extends Entity
{
    public function publishedPosts()
    {
        return $this->hasMany('App\Entities\Post')->where('published', 1);
    }

    public function recentPosts()
    {
        return $this->hasMany('App\Entities\Post')
                    ->where('created_at >', date('Y-m-d', strtotime('-30 days')));
    }
}
