<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class RemoveDuplicatedUsers prepares `email` field of the `users` table to be unique
 */
class RemoveDuplicatedUsers extends Migration
{
    /**
     * Finds duplicate users with the same email address
     *
     * @return void
     */
    public function up(): void
    {
        $allDuplicateUsers = DB::table('users')
            ->select('id', 'email')
            ->whereIn('email', function ($q) {
                $q->select('email')->from('users')->groupBy('email')->havingRaw('COUNT(*) > 1')->orderBy('id');
            })
            ->get();

        foreach ($allDuplicateUsers as $duplicateUser) {
            # group all the users with the same email address
            $usersWithSameEmail = $allDuplicateUsers->filter(function ($allDuplicateUsers) use ($duplicateUser) {
                return strtolower($allDuplicateUsers->email) === strtolower($duplicateUser->email);
            });

            if ($allDuplicateUsers->isNotEmpty() && $usersWithSameEmail->isNotEmpty()) {
                # removes $usersWithSameEmail from $allDuplicateUsers
                # to prevent repetition of chunks with the same emails
                $allDuplicateUsers = $allDuplicateUsers->filter(function ($allDuplicate) use ($usersWithSameEmail) {
                    return strtolower($allDuplicate->email) !== strtolower($usersWithSameEmail->first()->email);
                });
            }

            if ($usersWithSameEmail->isNotEmpty()) {
                $this->deleteDuplicates($usersWithSameEmail);
            }
        }
    }

    /**
     * Removes all provided $users except the first user from the database
     *
     * @param Collection $users with the same email address
     */
    private function deleteDuplicates(Collection $users): void
    {
        foreach ($users->except($users->keys()->first()) as $user) {
            App\GroupMembers::withTrashed()->where('user_id', $user->id)->forceDelete();
            App\FacebookGroups::withTrashed()->where('user_id', $user->id)->forceDelete();
            App\AutoResponder::withTrashed()->where('user_id', $user->id)->forceDelete();
            App\TeamMemberGroupAccess::where('user_id', $user->id)->forceDelete();
            App\OwnerToTeamMember::where('owner_id', $user->id)
                ->orWhere('team_member_id', $user->id)
                ->forceDelete();
            App\User::withTrashed()->find($user->id)->forceDelete();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        # We can't reverse the migration because we don't know which users were duplicated initially
    }
}
