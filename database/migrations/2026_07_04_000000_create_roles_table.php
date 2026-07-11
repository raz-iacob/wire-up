<?php

declare(strict_types=1);

use App\Services\RolePresets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique()->comment('Immutable machine identifier');
            $table->string('name');
            $table->json('abilities')->comment('Permission keys granted to this role');
            $table->boolean('bypass')->default(false)->comment('Full access; bypasses every ability check');
            $table->boolean('is_protected')->default(false)->comment('System role that cannot be deleted');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });

        $this->addRoles();
    }

    private function addRoles(): void
    {
        DB::table('roles')->insert(array_map(fn (array $preset): array => [
            'key' => $preset['key'],
            'name' => $preset['name'],
            'abilities' => json_encode($preset['abilities']),
            'bypass' => $preset['bypass'],
            'is_protected' => $preset['is_protected'],
            'created_at' => now(),
            'updated_at' => now(),
        ], RolePresets::all()));
    }
};
