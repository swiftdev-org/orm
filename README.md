# Eloquent-Style ORM Extension for CodeIgniter 4

A powerful ORM extension for CodeIgniter 4 that brings Laravel Eloquent-like functionality to your CI4 applications, including relationship mapping, eager loading, and lazy loading.

## Features

- 🚀 **Eloquent-style syntax** - Familiar API for Laravel developers
- 🔗 **Relationship mapping** - HasOne, HasMany, BelongsTo, BelongsToMany
- ⚡ **Eager loading** - Solve N+1 query problems with `with()`
- 📊 **Relationship counting** - Count relationships without loading data using `withCount()`
- 🏗️ **Nested relationships** - Load deep relationship trees
- 🎯 **Lazy loading** - Load relationships on-demand
- 🔧 **Convention over configuration** - Automatic foreign key resolution
- 📈 **Performance optimized** - Efficient query building and caching

## Installation

### 1. File Structure

Create the following directory structure in your CodeIgniter 4 project:

```
app/
├── Libraries/
│   └── ORM/
│       ├── ORMEntity.php
│       ├── ORMModel.php
│       ├── Relation.php
│       ├── HasOne.php
│       ├── HasMany.php
│       ├── BelongsTo.php
│       └── BelongsToMany.php
├── Entities/
│   ├── User.php
│   ├── Post.php
│   ├── Comment.php
│   ├── Profile.php
│   ├── Role.php
│   ├── Tag.php
│   ├── Category.php
│   └── Permission.php
└── Models/
    ├── UserModel.php
    ├── PostModel.php
    ├── CommentModel.php
    ├── ProfileModel.php
    ├── RoleModel.php
    ├── TagModel.php
    ├── CategoryModel.php
    └── PermissionModel.php
```

### 2. Copy Files

Copy all the provided PHP files to their respective directories:

1. **ORM Library Files** → `app/Libraries/ORM/`
2. **Entity Files** → `app/Entities/`
3. **Model Files** → `app/Models/`

### 3. Database Setup

Run the provided SQL schema to create the database tables:

```bash
mysql -u your_username -p your_database < database_schema.sql
```

Or create migrations based on the provided schema.

### 4. Configure Database

Ensure your `app/Config/Database.php` is properly configured:

```php
public array $default = [
    'DSN'          => '',
    'hostname'     => 'localhost',
    'username'     => 'your_username',
    'password'     => 'your_password',
    'database'     => 'your_database',
    'DBDriver'     => 'MySQLi',
    'DBPrefix'     => '',
    'pConnect'     => false,
    'DBDebug'      => true, // Set to false in production
    'charset'      => 'utf8mb4',
    'DBCollat'     => 'utf8mb4_unicode_ci',
    'swapPre'      => '',
    'encrypt'      => false,
    'compress'     => false,
    'strictOn'     => false,
    'failover'     => [],
    'port'         => 3306,
    'numberNative' => false,
];
```

## Quick Start

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

### Defining Relationships

In your Entity classes:

```php
<?php
namespace App\Entities;

use App\Libraries\ORM\ORMEntity;

class User extends ORMEntity
{
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

### Accessing Relationships

```php
// Eager loading
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

## Relationship Types

### HasOne

One-to-one relationships:

```php
public function profile()
{
    return $this->hasOne('App\Entities\Profile', 'user_id', 'id');
}
```

### HasMany

One-to-many relationships:

```php
public function posts()
{
    return $this->hasMany('App\Entities\Post', 'user_id', 'id');
}
```

### BelongsTo

Many-to-one relationships:

```php
public function user()
{
    return $this->belongsTo('App\Entities\User', 'user_id', 'id');
}
```

### BelongsToMany

Many-to-many relationships:

```php
public function roles()
{
    return $this->belongsToMany(
        'App\Entities\Role',    // Related entity
        'user_roles',           // Pivot table
        'user_id',              // Foreign key
        'role_id',              // Related key
        'id',                   // Local key
        'id'                    // Related local key
    );
}
```

## Advanced Features

### Nested Eager Loading

```php
$posts = model('PostModel')
    ->with(['user.profile', 'comments.user'])
    ->findAll();
```

### Relationship Counting

```php
$users = model('UserModel')
    ->withCount(['posts', 'comments'])
    ->findAll();

// Access counts
echo $user->posts_count;
echo $user->comments_count;
```

### Combining with Query Builder

```php
$posts = model('PostModel')
    ->with(['user', 'tags'])
    ->where('published', 1)
    ->where('created_at >', '2024-01-01')
    ->orderBy('created_at', 'DESC')
    ->findAll();
```

### Self-Referencing Relationships

```php
// In Comment entity
public function replies()
{
    return $this->hasMany('App\Entities\Comment', 'parent_id');
}

public function parent()
{
    return $this->belongsTo('App\Entities\Comment', 'parent_id');
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

## Testing

Create a test controller to verify your setup:

```php
<?php
namespace App\Controllers;

class TestController extends BaseController
{
    public function orm()
    {
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

## Best Practices

1. **Always use eager loading** when you know you'll need relationships
2. **Use `withCount()`** instead of loading full relationships when you only need counts
3. **Be specific** about which relationships you need
4. **Monitor query counts** in development using the debug toolbar
5. **Use lazy loading sparingly** to avoid N+1 problems
6. **Index your foreign keys** for better performance
7. **Consider caching** for frequently accessed data

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

## Troubleshooting

### Common Issues

1. **Relationship not loading**: Check entity class namespace and method name
2. **N+1 queries**: Use `with()` for eager loading
3. **Memory issues**: Use `withCount()` instead of loading large relationships
4. **Foreign key errors**: Verify table structure and foreign key constraints

### Debug Mode

Enable query logging to see what SQL is being generated:

```php
// In your controller
$db = \Config\Database::connect();
$db->query("SET SESSION sql_mode = ''");
log_message('info', $db->getLastQuery());
```

## Contributing

Feel free to contribute improvements, bug fixes, or additional features to this ORM extension.

## License

This project is open-sourced software licensed under the MIT license.

## Support

For questions and support, please check the usage examples and documentation provided in the code files.
