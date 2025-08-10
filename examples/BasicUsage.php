<?php

/**
 * Swift ORM - Basic Usage Examples
 *
 * This file demonstrates the fundamental concepts and basic usage patterns
 * of Swift ORM for CodeIgniter 4.
 */


/*
===========================================
BASIC ENTITY DEFINITION
===========================================
*/

use Swift\ORM\Entity;

class User extends Entity
{
    protected $attributes = [
        'id' => null,
        'name' => null,
        'email' => null,
        'active' => 1,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected $casts = [
        'id' => 'integer',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Define a simple relationship
    public function posts()
    {
        return $this->hasMany('App\Entities\Post');
    }
}

/*
===========================================
BASIC MODEL DEFINITION
===========================================
*/

use Swift\ORM\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'App\Entities\User';

    protected $allowedFields = [
        'name',
        'email',
        'active',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[100]',
        'email' => 'required|valid_email|is_unique[users.email]',
    ];

    // Custom scope method
    public function active()
    {
        return $this->where('active', 1);
    }
}

/*
===========================================
CONTROLLER EXAMPLES
===========================================
*/

use CodeIgniter\Controller;

class BasicExampleController extends Controller
{
    public function simpleFind()
    {
        $userModel = new UserModel();

        // Find a single user
        $user = $userModel->find(1);

        if ($user) {
            echo "User: " . $user->name . " (" . $user->email . ")";
        } else {
            echo "User not found";
        }
    }

    public function findAll()
    {
        $userModel = new UserModel();

        // Get all users
        $users = $userModel->findAll();

        foreach ($users as $user) {
            echo $user->name . " - " . $user->email . "<br>";
        }
    }

    public function withConditions()
    {
        $userModel = new UserModel();

        // Find users with conditions
        $activeUsers = $userModel
            ->where('active', 1)
            ->where('created_at >', '2024-01-01')
            ->orderBy('name', 'ASC')
            ->findAll();

        foreach ($activeUsers as $user) {
            echo "Active user: " . $user->name . "<br>";
        }
    }

    public function usingScopes()
    {
        $userModel = new UserModel();

        // Use custom scope method
        $activeUsers = $userModel->active()->findAll();

        foreach ($activeUsers as $user) {
            echo "Active user: " . $user->name . "<br>";
        }
    }

    public function createUser()
    {
        $userModel = new UserModel();

        // Create new user
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'active' => true,
        ];

        if ($userModel->insert($userData)) {
            $newUserId = $userModel->getInsertID();
            echo "User created with ID: " . $newUserId;
        } else {
            echo "Failed to create user: " . implode(', ', $userModel->errors());
        }
    }

    public function updateUser()
    {
        $userModel = new UserModel();

        // Update existing user
        $userId = 1;
        $updateData = [
            'name' => 'John Smith',
            'email' => 'johnsmith@example.com',
        ];

        if ($userModel->update($userId, $updateData)) {
            echo "User updated successfully";
        } else {
            echo "Failed to update user: " . implode(', ', $userModel->errors());
        }
    }

    public function deleteUser()
    {
        $userModel = new UserModel();

        // Delete user
        $userId = 1;

        if ($userModel->delete($userId)) {
            echo "User deleted successfully";
        } else {
            echo "Failed to delete user";
        }
    }

    /*
    ===========================================
    WORKING WITH ENTITIES
    ===========================================
    */

    public function entityManipulation()
    {
        $userModel = new UserModel();

        // Get user as entity
        $user = $userModel->find(1);

        if ($user) {
            // Access attributes
            echo "Original name: " . $user->name . "<br>";

            // Modify attributes
            $user->name = 'Jane Doe';
            $user->email = 'jane@example.com';

            // Save changes
            if ($userModel->save($user)) {
                echo "User updated: " . $user->name;
            }

            // Check if attribute has changed
            if ($user->hasChanged('name')) {
                echo "Name was changed";
            }

            // Get original value
            echo "Original email: " . $user->getOriginal('email');
        }
    }

    public function castingExample()
    {
        $userModel = new UserModel();
        $user = $userModel->find(1);

        if ($user) {
            // Type casting in action
            echo "User ID (integer): " . gettype($user->id) . " = " . $user->id . "<br>";
            echo "Active status (boolean): " . gettype($user->active) . " = " . ($user->active ? 'true' : 'false') . "<br>";
            echo "Created at (DateTime): " . get_class($user->created_at) . " = " . $user->created_at->format('Y-m-d H:i:s') . "<br>";
        }
    }

    /*
    ===========================================
    PAGINATION EXAMPLES
    ===========================================
    */

    public function paginationExample()
    {
        $userModel = new UserModel();

        // Using CodeIgniter's built-in pagination
        $users = $userModel->paginate(10);
        $pager = $userModel->pager;

        echo "<h3>Users (Page " . $pager->getCurrentPage() . ")</h3>";

        foreach ($users as $user) {
            echo $user->name . " - " . $user->email . "<br>";
        }

        // Pagination links
        echo $pager->links();
    }

    /*
    ===========================================
    VALIDATION EXAMPLES
    ===========================================
    */

    public function validationExample()
    {
        $userModel = new UserModel();

        // Try to create user with invalid data
        $invalidData = [
            'name' => 'A', // Too short
            'email' => 'invalid-email', // Invalid format
        ];

        if (!$userModel->insert($invalidData)) {
            echo "Validation failed:<br>";
            foreach ($userModel->errors() as $field => $error) {
                echo $field . ": " . $error . "<br>";
            }
        }

        // Create user with valid data
        $validData = [
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
        ];

        if ($userModel->insert($validData)) {
            echo "User created successfully!";
        }
    }

    /*
    ===========================================
    QUERY BUILDER INTEGRATION
    ===========================================
    */

    public function queryBuilderIntegration()
    {
        $userModel = new UserModel();

        // Complex queries using query builder
        $users = $userModel
            ->select('users.*, COUNT(posts.id) as post_count')
            ->join('posts', 'posts.user_id = users.id', 'left')
            ->where('users.active', 1)
            ->groupBy('users.id')
            ->having('post_count >', 0)
            ->orderBy('post_count', 'DESC')
            ->limit(10)
            ->findAll();

        foreach ($users as $user) {
            echo $user->name . " has " . $user->post_count . " posts<br>";
        }
    }

    /*
    ===========================================
    BATCH OPERATIONS
    ===========================================
    */

    public function batchOperations()
    {
        $userModel = new UserModel();

        // Insert multiple users
        $users = [
            ['name' => 'User 1', 'email' => 'user1@example.com'],
            ['name' => 'User 2', 'email' => 'user2@example.com'],
            ['name' => 'User 3', 'email' => 'user3@example.com'],
        ];

        if ($userModel->insertBatch($users)) {
            echo "Multiple users created successfully!<br>";
        }

        // Update multiple users
        $updates = [
            ['id' => 1, 'active' => 0],
            ['id' => 2, 'active' => 0],
            ['id' => 3, 'active' => 1],
        ];

        if ($userModel->updateBatch($updates, 'id')) {
            echo "Multiple users updated successfully!<br>";
        }

        // Delete multiple users
        $userIds = [4, 5, 6];

        if ($userModel->delete($userIds)) {
            echo "Multiple users deleted successfully!<br>";
        }
    }

    /*
    ===========================================
    TRANSACTION EXAMPLES
    ===========================================
    */

    public function transactionExample()
    {
        $db = \Config\Database::connect();
        $userModel = new UserModel();

        $db->transStart();

        try {
            // Create user
            $userData = [
                'name' => 'Transaction User',
                'email' => 'transaction@example.com',
            ];

            $userModel->insert($userData);
            $userId = $userModel->getInsertID();

            // Create related data (assuming we have a ProfileModel)
            // $profileModel = new ProfileModel();
            // $profileModel->insert([
            //     'user_id' => $userId,
            //     'bio' => 'Test bio'
            // ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                echo "Transaction failed!";
            } else {
                echo "Transaction completed successfully!";
            }

        } catch (\Exception $e) {
            $db->transRollback();
            echo "Transaction failed: " . $e->getMessage();
        }
    }

    /*
    ===========================================
    ERROR HANDLING
    ===========================================
    */

    public function errorHandlingExample()
    {
        $userModel = new UserModel();

        try {
            // Attempt operation that might fail
            $user = $userModel->find(999999); // Non-existent ID

            if (!$user) {
                throw new \Exception("User not found");
            }

        } catch (\Exception $e) {
            log_message('error', 'User operation failed: ' . $e->getMessage());
            echo "An error occurred: " . $e->getMessage();
        }

        // Check for model errors
        $invalidData = ['email' => 'invalid'];

        if (!$userModel->insert($invalidData)) {
            $errors = $userModel->errors();

            if (!empty($errors)) {
                echo "Validation errors occurred:<br>";
                foreach ($errors as $field => $message) {
                    echo "- {$field}: {$message}<br>";
                }
            }
        }
    }
}

/*
===========================================
USAGE TIPS AND BEST PRACTICES
===========================================
*/

/*
1. ENTITY BEST PRACTICES:
   - Always define $attributes with default values
   - Use $casts for proper type conversion
   - Keep business logic in entities when appropriate

2. MODEL BEST PRACTICES:
   - Define $allowedFields for mass assignment protection
   - Use validation rules for data integrity
   - Create custom scope methods for reusable queries
   - Use transactions for complex operations

3. PERFORMANCE TIPS:
   - Use select() to limit columns when not all are needed
   - Implement caching for frequently accessed data
   - Use pagination for large datasets
   - Monitor queries with debug toolbar

4. SECURITY CONSIDERATIONS:
   - Always validate user input
   - Use $allowedFields to prevent mass assignment vulnerabilities
   - Sanitize data before database operations
   - Use prepared statements (handled automatically by CI4)

5. DEBUGGING:
   - Enable query logging in development
   - Use try-catch blocks for error handling
   - Log errors for production debugging
   - Use CI4's debug toolbar for query analysis
*/
