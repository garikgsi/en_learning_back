<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserInfo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInfoModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_user_info_have_one_to_one_relationship(): void
    {
        $user = User::factory()->create();

        $info = $user->info()->create([
            'first_grade_year' => 2020,
        ]);

        $this->assertTrue($user->refresh()->info->is($info));
        $this->assertTrue($info->user->is($user));
    }

    public function test_user_grade_is_computed_from_first_grade_year(): void
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => now()->year - 5,
        ]);

        $this->assertSame(5, $user->grade);
    }

    public function test_user_can_have_only_one_user_info_record(): void
    {
        $user = User::factory()->create();

        UserInfo::query()->create([
            'user_id' => $user->id,
            'first_grade_year' => 2020,
        ]);

        $this->expectException(QueryException::class);

        UserInfo::query()->create([
            'user_id' => $user->id,
            'first_grade_year' => 2021,
        ]);
    }

    public function test_user_with_user_info_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $user->info()->create([
            'first_grade_year' => 2020,
        ]);

        $this->expectException(QueryException::class);

        $user->delete();
    }
}
