// database/seeders/RoleSeeder.php
use Spatie\Permission\Models\Role;

Role::create(['name' => 'student']);
Role::create(['name' => 'instructor']);
Role::create(['name' => 'admin']);