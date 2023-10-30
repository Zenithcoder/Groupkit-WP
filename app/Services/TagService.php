<?php

namespace App\Services;

use App\FacebookGroups;
use App\GroupMembers;
use App\Tag;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class TagService manages to import bulk/single tag(s)
 *
 * @package App\Services
 */
class TagService
{
    /**
     * 1. Stores or gets tags id
     * 2. Connects provided tags to all group members
     *
     * @param array $tags that will be added to all provided group members
     * @param array $groupMembersId to connect with provided tags
     * @param int $facebookGroupId for the tag creation and connection to the group member
     */
    public function bulkImport(array $tags, array $groupMembersId, int $facebookGroupId): void
    {
        try {
            DB::beginTransaction();

            $tagIds = $this->updateOrCreate($tags, $facebookGroupId);

            $this->upsert($tagIds, $groupMembersId, $facebookGroupId);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            info($e->getMessage());
            Bugsnag::notifyException($e);
        }
    }

    /**
     * Deletes tags from the group members
     *
     * @param array $tags that will be deleted from the group members
     * @param array $groupMembersId from which tags will be deleted
     * @param int $facebookGroupId for the tag deletion and connection to the group member
     */
    public function bulkDelete(array $tags, array $groupMembersId, int $facebookGroupId): void
    {
        try {
            DB::beginTransaction();

            $tagsIds = $this->getIds($tags, $facebookGroupId);

            $groupMembersIdsChunks = array_chunk($groupMembersId, config('database.connections.mysql.chunk_size'));

            foreach ($groupMembersIdsChunks as $groupMembersIdsChunk) {
                DB::table('group_members_tags')
                    ->where('group_id', $facebookGroupId)
                    ->whereIn('tag_id', $tagsIds)
                    ->whereIn('group_member_id', $groupMembersIdsChunk)
                    ->delete();
            }

            Tag::whereIn('id', $tagsIds)
                ->where('is_recommended', 0)
                ->doesntHave('members')
                ->get()
                ->chunk(config('database.connections.mysql.chunk_size'))
                ->each(function (Collection $chunk) {
                    Tag::whereIn('id', $chunk->pluck('id'))->delete();
                });

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            info($e->getMessage());
            Bugsnag::notifyException($e);
        }
    }

    /**
     * Returns id of the provided tags in the {@see FacebookGroups}
     *
     * @param array $tags contain labels for finding in the database
     * @param int $facebookGroupId for connection to the right tags
     *
     * @return array with id of all provided tags
     */
    public function getIds(array $tags, int $facebookGroupId): array
    {
        return Tag::where('group_id', $facebookGroupId)
            ->whereIn('label', $tags)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Gets id from the provided tags.
     * Provided tags will be updated/stored according to the current status in the database.
     *
     * @param array $tags including labels that are connected or for connecting to the group
     * @param int $facebookGroupId for connecting the tag to the {@see FacebookGroups}
     * @param bool $isRecommended field that will be applied to provided tags
     *
     * @return array containing id of each provided tag
     */
    public function updateOrCreate(array $tags, int $facebookGroupId, bool $isRecommended = false): array
    {
        collect($tags)
            ->map(function (string $tagLabel) use ($facebookGroupId, $isRecommended) {
                $formattedTags = [
                    'label' => $tagLabel,
                    'group_id' => $facebookGroupId,
                ];

                if ($isRecommended) {
                    $formattedTags['is_recommended'] = $isRecommended;
                }

                return $formattedTags;
            })
            ->chunk(config('database.connections.mysql.chunk_size'))
            ->each(function (Collection $chunk) use ($isRecommended) {
                $isRecommended
                    ? Tag::upsert($chunk->toArray(), ['label', 'group_id'], ['is_recommended'])
                    : Tag::insertOrIgnore($chunk->toArray());
            });

        return $this->getIds($tags, $facebookGroupId);
    }

    /**
     * Stores or updates inputted tags to the group member
     *
     * @param array $tags including all tags label selected for the group member
     * @param int $groupMemberId for connecting the tag to the {@see GroupMembers}
     * @param int $facebookGroupId for connecting the tag to the {@see FacebookGroups}
     */
    public function storeOrUpdate(array $tags, int $groupMemberId, int $facebookGroupId): void
    {
        $tagIds = $this->updateOrCreate($tags, $facebookGroupId);

        $this->upsert($tagIds, [$groupMemberId], $facebookGroupId);
    }

    /**
     * Imports all tags for all provided group members in parallel, so tags can be different from user to user
     *
     * @param array $tags including all tags label for all the group members.
     *                    The position of the tags in the array is the same as the position of the connected
     *                    group member in the $groupMembers array
     * @param array $groupMembers that will be connected to the tags
     * @param int $facebookGroupId for connecting tag to the {@see FacebookGroups}
     */
    public function bulkStoreOrUpdate(array $tags, array $groupMembers, int $facebookGroupId, int $userId): void
    {
        for ($i = 0; $i < count($tags); $i++) {
            $groupMember = GroupMembers::where('group_id', $facebookGroupId)
                ->where('user_id', $userId)
                ->where('fb_id', $groupMembers[$i]['fb_id'])
                ->first();

            if (!empty($tags) && $groupMember) {
                $this->storeOrUpdate($tags[$i], $groupMember->id, $facebookGroupId);
            }
        }
    }

    /**
     * Upserts all the tags to the group members
     *
     * @param array $tagIds represents all the {@see Tag} that will be connected to the associated group member(s)
     * @param array $groupMembersId that will be connected with the {@see Tag}
     * @param int $facebookGroupId that will be connected with the {@see Tag}
     */
    public function upsert(array $tagIds, array $groupMembersId, int $facebookGroupId): void
    {
        $groupMemberTags = [];

        foreach ($groupMembersId as $groupMemberId) {
            foreach ($tagIds as $tagId) {
                $groupMemberTags[] = [
                    'tag_id' => $tagId,
                    'group_id' => $facebookGroupId,
                    'group_member_id' => $groupMemberId,
                ];
            }
        }

        $groupMembersTagsChunks = array_chunk(
            $groupMemberTags,
            config('database.connections.mysql.chunk_size')
        );

        foreach ($groupMembersTagsChunks as $groupMembersTagsChunk) {
            DB::table('group_members_tags')->upsert($groupMembersTagsChunk, ['tag_id', 'group_member_id']);
        }
    }

    /**
     * Updates/creates tags and recommended tags
     * Connects tags to the group members
     *
     * @param array $tags with:
     * optional recommended_tags_to_add to be stored as group recommended tags,
     * optional recommended_tags_to_delete that will be removed from group recommended tags,
     * optional tags_to_add for the provided $membersIds,
     * optional tags_to_delete from the provided $membersIds
     * @param int $groupId for what recommended tags will be added/deleted and new tags stored
     * @param array $memberIds that provided recommended tags or tags will be applied to or removed from,
     *
     * @throws Exception if an error occurs on MySQL import/update queries
     */
    public function manageTags(array $tags, int $groupId, array $memberIds): void
    {
        try {
            DB::beginTransaction();

            if (array_key_exists('recommended_tags_to_add', $tags)) {
                $this->updateOrCreate(
                    $tags['recommended_tags_to_add'],
                    $groupId,
                    true
                );
            }

            if (array_key_exists('recommended_tags_to_delete', $tags)) {
                $this->deleteRecommendedTags(
                    $tags['recommended_tags_to_delete'],
                    $groupId
                );
            }

            if (array_key_exists('tags_to_add', $tags)) {
                $this->bulkImport(
                    $tags['tags_to_add'],
                    $memberIds,
                    $groupId
                );
            }

            if (array_key_exists('tags_to_delete', $tags)) {
                $this->bulkDelete(
                    $tags['tags_to_delete'],
                    $memberIds,
                    $groupId
                );
            }

            DB::commit();
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Deletes recommended tags by group ID and tag label, if the tag
     * doesn't belong to the group (doesn't have members).
     *
     * @param array $recommendedTagsLabel to be deleted
     * @param integer $groupId of the {@see \App\FacebookGroups} which tags will be deleted
     */
    public function deleteRecommendedTags(array $recommendedTagsLabel, int $groupId): void
    {
        #Remove all recommended tags that are not used by any other group member
        Tag::where('group_id', $groupId)
            ->whereIn('label', $recommendedTagsLabel)
            ->doesntHave('members')
            ->get()
            ->chunk(config('database.connections.mysql.chunk_size'))
            ->each(function ($chunk) {
                Tag::whereIn('id', $chunk->pluck('id'))->delete();
            });

        # Remove recommended flag from tags that are used by group members
        Tag::where('group_id', $groupId)
            ->whereIn('label', $recommendedTagsLabel)
            ->has('members')
            ->get()
            ->chunk(config('database.connections.mysql.chunk_size'))
            ->each(function ($chunk) {
                Tag::whereIn('id', $chunk->pluck('id'))->update(['is_recommended' => 0]);
            });
    }
}
