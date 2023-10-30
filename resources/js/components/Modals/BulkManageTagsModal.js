import Modal from 'react-bootstrap/Modal';
import TagsInput from 'react-tagsinput';
import React, { Component } from 'react';
import { API } from "../Api";
import swal from 'sweetalert';
import { isEqual } from 'lodash-es';
import RecommendedTags from "../RecommendedTags";
import whenJobComplete from '../AsyncJobs';

/**
 * Class BulkManageTagsModal manages bulk adding, removing tags, adding recommended tags, deleting recommended
 * from the provided group members
 */
class BulkManageTagsModal extends Component {

    /**
     * Initializes state and props for the component
     *
     * @param {object} props defined on the component tag
     */
    constructor(props) {
        super(props);

        this.state = {
            tags_to_add: [],
            members_tags: [],
            recommended_tags_to_add: [],
            group_recommended_tags: [],
            recommended_tags_input: "",
        }
    }

    /**
     * Prepares all tags and tags data for submission
     *
     * @return {{tags_to_add: ([]|*), recommended_tags_to_add: ([]|*), group_id: integer, selected_member_ids: [],
     * excluded_member_ids: [], is_multi_page_select_all: boolean, tags_to_delete: ([]|null),
     * recommended_tags_to_delete: ([]|null)}}
     */
    prepareTagsForSubmit() {
        const membersIdsAndTags = {
            ...this.props.filters,
            tags_to_add: this.state.tags_to_add,
            recommended_tags_to_add: this.state.recommended_tags_to_add,
            selected_member_ids: Array.from(this.props.filters.selected_member_ids),
            excluded_member_ids: Array.from(this.props.filters.excluded_member_ids),
            tags_to_delete: null,
            recommended_tags_to_delete: null,
        }

        const tagsToDelete = this.state.members_tags.filter((tag) => !this.state.tags_to_add.includes(tag));
        const recommendedTagsToDelete = this.state.group_recommended_tags.filter((tag) => {
            return !this.state.recommended_tags_to_add.includes(tag);
        });

        if (tagsToDelete) {
            membersIdsAndTags.tags_to_delete = [...tagsToDelete];
        }

        if (recommendedTagsToDelete) {
            membersIdsAndTags.recommended_tags_to_delete = [...recommendedTagsToDelete];
        }

        return membersIdsAndTags;
    }

    /**
     * Sends data to the group members tags API
     */
    submit() {
        if (!this.requestDataIsValid()) {
            return swal('Tags are not added or deleted. Please delete or add tags and try again');
        }

        const tagsRequestData = this.prepareTagsForSubmit();

        API
            .sendTagsToTheGroupMembers(tagsRequestData)
            .then(({ data: {async = false, message = '', job_id = null}, status}) => {
                if (status === 200) {
                    if (async) {
                        swal(
                            'Bulk action for adding tags to the members is scheduled. You will be notified once it is completed.',
                            {icon: 'success'}
                        );
                        whenJobComplete(job_id)
                            .then(() => {
                                swal(message, {icon: 'success'});
                                this.props.reloadMembers();
                            });
                    } else {
                        swal(message);
                        this.props.reloadMembers();
                    }
                    this.closeModal();
                    this.props.resetPageToDefault();
                } else {
                    throw new Error(message)
                }

            })
            .catch(e => swal(e.message, { icon: 'error' }));
    }

    /**
     * Fetches the tags for the selected members.
     *
     * @return {void}
     */
    async getMembersTagsList() {
        try {
            const res = await API.getMembersTagsList({...this.props.filters});
            if (res.status === 200) {
                this.setState({ tags_to_add: res.data });
            }
        } catch (error) {
            swal("Can't fetch the tags for the members.", {icon: 'error'})
        }
    }

    /**
     * Determines if request data is valid
     *
     * @return {boolean} true if is valid, otherwise false
     */
    requestDataIsValid() {
        return this.state.tags_to_add.length
            || !isEqual(this.state.tags_to_add, this.state.members_tags)
            || this.state.recommended_tags_to_add.length
            || !isEqual(this.state.recommended_tags_to_add, this.state.group_recommended_tags)
        ;
    }

    /**
     * Sends provided tag to the {@see handleChangeTags} method
     *
     * @param {string} tag that will be added in the tags_to_add state
     */
    addTag(tag) {
        let tags = this.state.tags_to_add;
        tags.push(tag);

        this.handleChangeTags(tags);
    }

    /**
     * Adds or removes tags on {@see TagsInput} component change
     *
     * @param {array} tags that component has
     */
    handleChangeTags(tags) {
        this.setState({ tags_to_add: [...new Set(tags)] });
    }

    /**
     * Adds recommended tags on @addTag event from {@see RecommendedTags} component
     *
     * @param {Array} tags that will be added as group recommended tags
     */
    addRecommendedTag(tags) {
        this.setState({ recommended_tags_to_add: tags });
    }

    /**
     * Removes a tag from a current group recommended tag
     * on @removeTag event from {@see RecommendedTags} component
     *
     * @param {string} removedTag from the group recommended tags
     */
    removeRecommendedTag(removedTag) {
        this.setState({ recommended_tags_to_add: this.state.recommended_tags_to_add.filter(tag => tag !== removedTag)});
    }

    /**
     * Removes all group recommended tags on @removeTags event from {@see RecommendedTags} component
     */
    removeAllRecommendedTags() {
        this.setState({ recommended_tags_to_add: [] });
    }

    /**
     * Closes this component
     */
    closeModal() {
        this.props.hideModal();
    }

    /**
     * Sets group recommended tags
     */
    setGroupRecommendedTags() {
        const groupRecommendedTags = this.props.recommendedTags.map(tag => tag.label);

        this.setState({ recommended_tags_to_add: groupRecommendedTags });
        this.setState({ group_recommended_tags: groupRecommendedTags });
    }

    /**
     * Sets distinct tags of the provided members
     */
    async setTags() {
        await this.getMembersTagsList();

        this.setState({ members_tags: [...this.state.tags_to_add] });
    }

    /**
     * Sets members tags when component load
     */
    componentDidMount() {
        this.setTags();
        this.setGroupRecommendedTags();
    }

    /**
     * Returns HTML content of the component
     *
     * @return {JSX.Element} containing HTML with bind data
     */
    render() {
        return (
            <Modal
                dialogClassName="modal-90w"
                show={true}
                onHide={this.closeModal.bind(this)}
                backdrop="static"
            >
                <Modal.Header closeButton>
                    <Modal.Title>ADD/REMOVE TAGS</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <div className="m-2">
                        {
                            this.props.selectedMembersNumber > process.env.MIX_GOOGLE_SHEET_MAXIMUM_MEMBERS_NUMBER_TO_SEND
                            && this.props.hasConnectedGoogleSheetIntegration
                            &&
                            <div className="form-group text-center">
                                Tags can't be send to the GoogleSheet document since selected group members are over the&nbsp;
                                {process.env.MIX_GOOGLE_SHEET_MAXIMUM_MEMBERS_NUMBER_TO_SEND} limit
                            </div>
                        }
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">Tags</label>
                            <div className="col-sm-9">
                                <TagsInput
                                    placeholder="Tags"
                                    value={this.state.tags_to_add}
                                    onChange={this.handleChangeTags.bind(this)}
                                />
                            </div>
                        </div>
                    {
                        <RecommendedTags
                            tags={this.props.recommendedTags}
                            addTag={this.addRecommendedTag.bind(this)}
                            onTagClick={this.addTag.bind(this)}
                            removeTag={this.removeRecommendedTag.bind(this)}
                            removeTags={this.removeAllRecommendedTags.bind(this)}
                        />
                    }
                    </div>
                </Modal.Body>
                <Modal.Footer className="footer-center">
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={this.submit.bind(this)}
                    >
                        Save
                    </button>
                    <button
                        type="button"
                        className="btn btn-light"
                        onClick={this.closeModal.bind(this)}
                    >
                        Close
                    </button>
                </Modal.Footer>
            </Modal>
        );
    }
}

export default BulkManageTagsModal;
