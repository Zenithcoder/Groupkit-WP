<?php

namespace Tests\Unit\app\Services;

use App\FacebookGroups;
use App\GroupMembers;
use App\Services\TagService;
use App\Tag;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Class TagServiceTest adds test coverage for {@see TagService}
 *
 * @package Tests\Unit\app\Services
 * @coversDefaultClass \App\Services\TagService
 */
class TagServiceTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @test
     * that storeOrUpdate:
     * 1. Stores new tags in the database
     * 2. Adds new and existing tags to the group member
     *
     * @covers ::storeOrUpdate
     */
    public function storeOrUpdate_withVariousTags_connectTagsToGroupMember()
    {
        $facebookGroup = FacebookGroups::factory()->create();
        $groupMember = GroupMembers::factory()->create(['group_id' => $facebookGroup->id]);
        $existingTags = Tag::factory(5)->create(['group_id' => $facebookGroup->id]);
        $newTags = ['Message', 'RootAccess'];

        $result = app(TagService::class)->storeOrUpdate(
            array_merge($existingTags->pluck('label')->toArray(), $newTags),
            $groupMember->id,
            $facebookGroup->id
        );

        $this->assertNull($result);

        foreach ($newTags as $newTag) {
            $this->assertDatabaseHas('tags', [
               'label' => $newTag,
               'group_id' => $facebookGroup->id,
            ]);

            $this->assertDatabaseHas('group_members_tags', [
                'tag_id' => Tag::where('label', $newTag)->where('group_id', $facebookGroup->id)->first()->id,
                'group_id' => $facebookGroup->id,
                'group_member_id' => $groupMember->id,
            ]);
        }

        foreach ($existingTags as $existingTag) {
              $this->assertDatabaseHas('group_members_tags', [
                'tag_id' => $existingTag->id,
                'group_id' => $facebookGroup->id,
                'group_member_id' => $groupMember->id,
              ]);
        }
    }

    /**
     * @test
     * that bulkStoreOrUpdate
     * 1. Stores new tags in the database
     * 2. Connects group members with the tags
     *
     * @covers ::bulkStoreOrUpdate
     */
    public function bulkStoreOrUpdate_always_storesTagsForProvidedMembers()
    {
        $user = $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $groupMembers = GroupMembers::factory(10)->create(['group_id' => $facebookGroup->id, 'user_id' => $user->id]);

        $existingTags = Tag::factory(2)->create(['group_id' => $facebookGroup->id]);
        $existingTagsLabel = $existingTags->pluck('label')->toArray();
        $newTags = ['Message', 'RootAccess'];
        $allTags = array_merge($existingTagsLabel, $newTags);

        $groupMembersTags = [];
        $groupMembersCount = $groupMembers->count();
        for ($i = 0; $i < $groupMembersCount; $i++) {
            $groupMembersTags[] = rand(0, 1) ? $newTags : $existingTagsLabel;
        }

        app(TagService::class)
            ->bulkStoreOrUpdate(
                $groupMembersTags,
                $groupMembers->toArray(),
                $facebookGroup->id,
                $user->id
            );

        foreach ($allTags as $tagLabel) {
            $this->assertDatabaseHas('tags', [
                'label' => $tagLabel,
                'group_id' => $facebookGroup->id,
            ]);
        }

        $groupMembersTagsCount = count($groupMembersTags);
        for ($i = 0; $i < $groupMembersTagsCount; $i++) {
            foreach ($groupMembersTags[$i] as $groupMembersTag) {
                $tag = Tag::where('label', $groupMembersTag)->where('group_id', $facebookGroup->id)->first();
                $this->assertDatabaseHas('group_members_tags', [
                   'group_id' => $facebookGroup->id,
                   'group_member_id' => $groupMembers[$i]->id,
                   'tag_id' => $tag->id,
                ]);
            }
        }
    }

    /**
     * @test
     * that upsert connects all group members with provided tags
     *
     * @covers ::upsert
     */
    public function upsert_always_connectsAllGroupMembersWithTags()
    {
        $user = $this->actingAsApiUser();
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $groupMembers = GroupMembers::factory(10)->create(['group_id' => $facebookGroup->id, 'user_id' => $user->id]);

        $tags = Tag::factory(10)->create(['group_id' => $facebookGroup->id]);

        app(TagService::class)
            ->upsert(
                $tags->pluck('id')->toArray(),
                $groupMembers->pluck('id')->toArray(),
                $facebookGroup->id,
            );

        foreach ($groupMembers as $groupMember) {
            foreach ($tags as $tag) {
                $this->assertDatabaseHas('group_members_tags', [
                    'tag_id' => $tag->id,
                    'group_member_id' => $groupMember->id,
                    'group_id' => $facebookGroup->id,
                ]);
            }
        }
    }
}
