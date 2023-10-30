<?php

use App\GroupMembers;
use App\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Class MoveAllTagsFromGroupMembersToTagsTable extracts tags from `group_members` to `tags` table
 * and setup connection between them
 */
class MoveAllTagsFromGroupMembersToTagsTable extends Migration
{
    /**
     * Moves all tags from `group_members` table to `tags` table
     * Connects `tags` with `group_members` in `group_members_tags` table
     *
     * @return void
     */
    public function up()
    {
        $groupMembers = GroupMembers::select('id', 'group_id', 'tags')
            ->whereNotNull('tags')
            ->where('tags', '!=', '[]');

        $groupMembersTags = [];
        foreach ($groupMembers->get() as $groupMember) {
            foreach (json_decode($groupMember->tags) as $label) {
                $tag = Tag::where('label', $label)->where('group_id', $groupMember->group_id)->first();
                if (!$tag) {
                    $tag = Tag::create(['label' => $label, 'group_id' => $groupMember->group_id]);
                }

                $groupMembersTags[] = [
                    'group_member_id' => $groupMember->id,
                    'tag_id'          => $tag->id,
                    'group_id'        => $groupMember->group_id,
                    'created_at'      => now(),
                ];
            }
        }

        $groupMembersTagsChunks = array_chunk($groupMembersTags, config('database.connections.mysql.chunk_size'));

        foreach ($groupMembersTagsChunks as $groupMembersTagsChunk) {
            DB::table('group_members_tags')->insert($groupMembersTagsChunk);
        }

        $groupMembers->update(['tags' => null]);
    }

    /**
     * Moves back tags from `tags` table
     * Truncates `group_members_tags` connection table
     *
     * @return void
     */
    public function down()
    {
        $tags = DB::table('group_members_tags')
            ->join('tags', 'group_members_tags.tag_id', '=', 'tags.id')
            ->get();

        foreach ($tags as $tag) {
            $groupMember = GroupMembers::find($tag->group_member_id);

            if ($groupMember->tags && $groupMember->tags !== '[]') {
                $groupMemberTags = json_decode($groupMember->tags);
                $groupMemberTags[] = $tag->label;
                $groupMember->tags = '["' . implode('", "', $groupMemberTags) . '"]';
            } else {
                $groupMember->tags = '["' . $tag->label . '"]';
            }

            $groupMember->save();
        }

        DB::table('group_members_tags')->truncate();
    }
}
