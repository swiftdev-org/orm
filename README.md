# Eloquent-Style ORM Extension for CodeIgniter 4

A powerful ORM extension for CodeIgniter 4 that brings Laravel Eloquent-like functionality to your CI4 applications, including relationship mapping, eager loading, lazy loading, and intelligent code generators.

## Features

- 🚀 **Eloquent-style syntax** - Familiar API for Laravel developers
- 🔗 **Relationship mapping** - HasOne, HasMany, BelongsTo, BelongsToMany
- 📊 **Relationship counting** - Count relationships without loading data using `withCount()`
- 🏗️ **Nested relationships** - Load deep relationship trees
- ⚡ **Eager loading** - Solve N+1 query problems with `with()`
- 🎯 **Lazy loading** - Load relationships on-demand
- 🔧 **Convention over configuration** - Automatic foreign key resolution
- 📈 **Performance optimized** - Efficient query building and caching
- 🤖 **Smart generators** - Auto-generate Models and Entities from database schema
- 🔍 **Relationship detection** - Automatically discover and generate relationship methods

## Why Swift ORM?

### Traditional CodeIgniter 4 Way:
```php
// Multiple queries and manual joins
$userModel = new UserModel();
$postModel = new PostModel();
$users = $userModel->findAll();

foreach ($users as $user) {
    $user->posts = $postModel->where('user_id', $user->id)->findAll();
    // N+1 query problem!
}
```

### Swift ORM Way:
```php
// Single optimized query with relationships
$users = model('UserModel')->with(['posts', 'profile'])->findAll();

foreach ($users as $user) {
    echo $user->name;
    echo $user->profile->bio;
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}
```

## Installation

### Option 1: Manual Installation (Recommended for Development)

#### 1. Extract Files
Extract Swift ORM files to your CodeIgniter 4 project:

```
app/
├── Entities/
│   └── (Generated entities will go here by default)
├── Models/
│   └── (Generated models will go here by default)
└── ThirdParty/
    └── Swift/
        └── ORM/
            ├── LICENSE
            ├── README.md
            ├── examples/
            │   ├── AdvancedQueries.php
            │   ├── BasicUsage.php
            │   ├── PerformanceExamples.php
            │   └── RelationshipExamples.php
            └── src/
                ├── Entity.php
                ├── Model.php
                ├── Commands/
                │   ├── MakeEntity.php
                │   └── MakeModel.php
                ├── Config/
                │   └── Commands.php
                └── Relations/
                    ├── BelongsTo.php
                    ├── BelongsToMany.php
                    ├── HasMany.php
                    ├── HasOne.php
                    └── Relation.php
```

#### 2. Update Autoloader Config

Add the namespace to your `app/Config/Autoload.php`:

```php
<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

class Autoload extends AutoloadConfig
{
    public $psr4 = [
        APP_NAMESPACE => APPPATH,
        'Config'       => APPPATH . 'Config',
        'Swift\ORM'    => APPPATH . 'ThirdParty/Swift/ORM/src',
    ];
    
    // ... rest of config
}
```

### Option 2: Composer Package Installation

```bash
# This will be available when published as a package
composer require swiftdev-org/orm
```


## Quick Start

### Using Code Generators (Recommended)

The fastest way to get started is using the intelligent code generators:

```bash
# Generate models and entities for all tables
php spark make:model --all --entity

# Or generate for a specific table
php spark make:model User --entity --table=users
```

### Manual Setup

If you prefer to create files manually:

#### Define Entities

```php
<?php
namespace App\Entities;

use Swift\ORM\Entity;

class User extends Entity
{
    protected $attributes = [
        'id' => null,
        'name' => null,
        'email' => null,
        'created_at' => null,
        'updated_at' => null,
    ];
    
    // One-to-many
    public function posts()
    {
        return $this->hasMany('App\Entities\Post');
    }
    
    // One-to-one
    public function profile()
    {
        return $this->hasOne('App\Entities\Profile');
    }
    
    // Many-to-many
    public function roles()
    {
        return $this->belongsToMany('App\Entities\Role', 'user_roles');
    }
}
```

#### Create Models

```php
<?php
namespace App\Models;

use Swift\ORM\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $returnType = 'App\Entities\User';
    protected $allowedFields = ['name', 'email'];
    protected $useTimestamps = true;
}
```

### Basic Usage

```php
<?php
namespace App\Controllers;

class BlogController extends BaseController
{
    public function index()
    {
        $postModel = new \App\Models\PostModel();
        
        // Load posts with authors and comment counts
        $posts = $postModel
            ->with(['user', 'tags'])
            ->withCount(['comments'])
            ->where('published', 1)
            ->findAll();
        
        return view('blog/index', ['posts' => $posts]);
    }
}
```

## Code Generators

Swift ORM includes powerful code generators that analyze your database and create Models and Entities with relationships automatically detected.

### Generate Models

```bash
# Single model
php spark make:model User
php spark make:model User --table=users
php spark make:model User --namespace=App\\Blog\\Models

# With entity
php spark make:model User --entity

# All tables
php spark make:model --all

# With custom suffix
php spark make:model User --suffix=Model
```

### Generate Entities

```bash
# Single entity  
php spark make:entity User
php spark make:entity User --table=users
php spark make:entity User --namespace=App\\Blog\\Entities

# With model
php spark make:entity User --model

# All tables
php spark make:entity --all

# With custom suffix
php spark make:entity User --suffix=Entity
```

### What Gets Auto-Generated

The generators analyze your database schema and automatically detect:

- **Table structure** - Fields, types, constraints
- **Primary keys** - Auto-detection of primary key fields
- **Timestamps** - created_at, updated_at fields
- **Soft deletes** - deleted_at fields
- **Foreign keys** - belongsTo relationships
- **Reverse relationships** - hasMany/hasOne relationships  
- **Pivot tables** - belongsToMany relationships
- **Field casting** - Automatic type casting (int, bool, datetime, etc.)
- **Validation rules** - Basic rules based on field types
- **Fillable fields** - Excluding primary keys and timestamps

### Generator Examples

```bash
# Generate everything for a blog system
php spark make:model Post --entity --namespace=App\\Blog\\Models
php spark make:entity Comment --model --table=blog_comments

# Regenerate all files (useful during development)
php spark make:model --all --entity --force
```

## Working with Relationships

### Defining Relationships

Swift ORM supports all major relationship types with intuitive method names:

```php
class User extends Entity
{
    // One-to-one: User has one profile
    public function profile()
    {
        return $this->hasOne('App\Entities\Profile');
    }
    
    // One-to-many: User has many posts
    public function posts()
    {
        return $this->hasMany('App\Entities\Post');
    }
    
    // Many-to-many: User has many roles
    public function roles()
    {
        return $this->belongsToMany('App\Entities\Role', 'user_roles');
    }
}

class Post extends Entity
{
    // Many-to-one: Post belongs to user
    public function user()
    {
        return $this->belongsTo('App\Entities\User');
    }
    
    // One-to-many: Post has many comments
    public function comments()
    {
        return $this->hasMany('App\Entities\Comment');
    }
}
```

### Using Relationships

```php
// Eager loading (recommended)
$users = model('UserModel')->with(['posts', 'profile'])->findAll();

foreach ($users as $user) {
    echo $user->name;
    echo $user->profile->bio;
    
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}

// Lazy loading
$user = model('UserModel')->find(1);
$posts = $user->posts; // Loads when accessed
```

## Advanced Features

### Nested Eager Loading

Load relationships of relationships:

```php
$posts = model('PostModel')
    ->with(['user.profile', 'comments.user'])
    ->findAll();

// Access nested data
foreach ($posts as $post) {
    echo $post->user->profile->bio;
    
    foreach ($post->comments as $comment) {
        echo $comment->user->name;
    }
}
```

### Relationship Counting

Count related records without loading them:

```php
$users = model('UserModel')
    ->withCount(['posts', 'comments'])
    ->findAll();

foreach ($users as $user) {
    echo "{$user->name} has {$user->posts_count} posts";
    echo "and {$user->comments_count} comments";
}
```

### Complex Queries

Combine relationships with query builder methods:

```php
$posts = model('PostModel')
    ->with(['user', 'tags'])
    ->where('published', 1)
    ->where('created_at >', '2024-01-01')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->findAll();
```

### Self-Referencing Relationships

Handle hierarchical data:

```php
class Comment extends Entity
{
    public function replies()
    {
        return $this->hasMany('App\Entities\Comment', 'parent_id');
    }
    
    public function parent()
    {
        return $this->belongsTo('App\Entities\Comment', 'parent_id');
    }
}

class Category extends Entity
{
    public function children()
    {
        return $this->hasMany('App\Entities\Category', 'parent_id');
    }
    
    public function parent()
    {
        return $this->belongsTo('App\Entities\Category', 'parent_id');
    }
}
```

## Performance Optimization

### Solving N+1 Problems

❌ **Bad** (N+1 queries):
```php
$posts = model('PostModel')->findAll();
foreach ($posts as $post) {
    echo $post->user->name; // Triggers a query for each post
}
```

✅ **Good** (2 queries):
```php
$posts = model('PostModel')->with(['user'])->findAll();
foreach ($posts as $post) {
    echo $post->user->name; // No additional queries
}
```

### Query Monitoring

Enable debug toolbar in development:

```php
// In app/Config/Filters.php
public array $globals = [
    'before' => [
        'toolbar'
    ],
];
```

### Best Practices

1. **Always use generators** for initial setup
2. **Use eager loading** when you know you'll need relationships
3. **Use `withCount()`** instead of loading full relationships when you only need counts
4. **Be specific** about which relationships you need
5. **Monitor query counts** in development
6. **Use lazy loading sparingly** to avoid N+1 problems
7. **Index your foreign keys** for better performance
8. **Consider caching** for frequently accessed data

## Examples

Check out the comprehensive examples in `app/ThirdParty/Swift/ORM/examples/`:

- **BasicUsage.php** - Getting started with Swift ORM
- **RelationshipExamples.php** - All relationship types in action
- **AdvancedQueries.php** - Complex queries and nested loading
- **PerformanceExamples.php** - Optimization techniques

## Repository Pattern Example

For complex business logic, consider using the repository pattern:

```php
<?php
namespace App\Repositories;

class UserRepository
{
    protected $userModel;
    
    public function __construct()
    {
        $this->userModel = new \App\Models\UserModel();
    }
    
    public function getUserDashboard(int $userId)
    {
        return $this->userModel
            ->with([
                'posts.comments',
                'profile',
                'roles.permissions'
            ])
            ->withCount(['posts', 'comments'])
            ->find($userId);
    }
    
    public function getActiveAuthors()
    {
        return $this->userModel
            ->with(['profile'])
            ->withCount(['posts'])
            ->where('active', 1)
            ->having('posts_count >', 0)
            ->findAll();
    }
}
```

## Testing Your Setup

Create a test controller to verify everything works:

```php
<?php
namespace App\Controllers;

class TestController extends BaseController
{
    public function orm()
    {
        // Test the generators first
        $output = shell_exec('php spark make:model TestUser --entity --force 2>&1');
        echo "<pre>Generator output:\n" . $output . "</pre>";
        
        // Test the ORM
        $userModel = new \App\Models\UserModel();
        
        $users = $userModel
            ->with(['posts', 'profile'])
            ->withCount(['comments'])
            ->findAll();
        
        foreach ($users as $user) {
            echo "<h3>{$user->name}</h3>";
            echo "<p>Posts: " . count($user->posts) . "</p>";
            echo "<p>Comments: {$user->comments_count}</p>";
            echo "<p>Bio: " . ($user->profile->bio ?? 'No bio') . "</p>";
            echo "<hr>";
        }
    }
}
```

## Migration from Standard CodeIgniter

### Before (Standard CI4):
```php
class UserController extends BaseController
{
    public function index()
    {
        $userModel = new UserModel();
        $postModel = new PostModel();
        
        $users = $userModel->findAll();
        
        foreach ($users as &$user) {
            $user['posts'] = $postModel->where('user_id', $user['id'])->findAll();
        }
        
        return view('users', ['users' => $users]);
    }
}
```

### After (Swift ORM):
```php
class UserController extends BaseController
{
    public function index()
    {
        $users = model('UserModel')
            ->with(['posts'])
            ->withCount(['comments'])
            ->findAll();
        
        return view('users', ['users' => $users]);
    }
}
```

## Troubleshooting

### Common Issues

1. **Commands not found**: Ensure commands are registered in `app/Config/Commands.php`
2. **Autoloading issues**: Check your `app/Config/Autoload.php` PSR-4 mapping
3. **Database connection**: Verify your database configuration
4. **Relationship not loading**: Check entity class namespace and method name
5. **N+1 queries**: Use `with()` for eager loading
6. **Memory issues**: Use `withCount()` instead of loading large relationships
7. **Foreign key errors**: Verify table structure and foreign key constraints

### Debug Mode

Enable query logging to see what SQL is being generated:

```php
// In your controller
$db = \Config\Database::connect();
log_message('info', $db->getLastQuery());
```

### Generator Issues

If generators aren't working:

1. Check database connection
2. Verify table exists
3. Ensure proper permissions for file creation
4. Use `--force` to overwrite existing files
5. Check `app/Config/Commands.php` registration

## Contributing

Feel free to contribute improvements, bug fixes, or additional features to this Swift ORM extension.

## License

This project is open-sourced software licensed under the MIT license.

## Support

For questions and support:
- Check the usage examples and documentation provided
- Review the generated code for relationship patterns
- Use the debug toolbar to monitor queries
- Test with the provided example controllers
