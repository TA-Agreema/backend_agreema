<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically sync/register all permissions defined in the routes (middleware)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Scanning routes for permissions...');

        // Clear cache first
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $routes = Route::getRoutes();
        $permissionsFound = [];

        foreach ($routes as $route) {
            $middlewares = $route->middleware();

            if (is_array($middlewares)) {
                foreach ($middlewares as $middleware) {
                    // Check if middleware starts with "permission:"
                    if (str_starts_with($middleware, 'permission:')) {
                        // Extract permission string(s)
                        $permissionString = str_replace('permission:', '', $middleware);

                        // Handle multiple permissions separated by pipe '|' (e.g., permission:read.all.roles|read.permission)
                        $permissionList = explode('|', $permissionString);

                        foreach ($permissionList as $perm) {
                            $permissionsFound[] = trim($perm);
                        }
                    }
                }
            }
        }

        // Get unique permissions
        $permissionsFound = array_unique($permissionsFound);
        $count = 0;

        foreach ($permissionsFound as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);

            if ($permission->wasRecentlyCreated) {
                $this->line("Created new permission: <info>{$permissionName}</info>");
                $count++;
            }
        }

        // auto assign permission to admin role
        $this->info("Assigning all permissions to 'admin' role...");

        $adminRole = Role::where('name', 'admin')->orWhere('name', 'Admin')->first();

        if ($adminRole) {
            // assign permission to admin role
            $adminRole->syncPermissions(Permission::all());
            $this->info("Successfully assigned all permissions to {$adminRole->name} role!");
        } else {
            $this->warn("Role 'admin' not found in database. Skipping automatic assignment.");
        }

        $this->info("Sync completed! Total unique permissions found: " . count($permissionsFound) . ". Newly created: {$count}.");
    }
}
