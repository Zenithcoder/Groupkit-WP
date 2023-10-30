import React, { Component } from 'react';
import Modal from 'react-bootstrap/Modal';
import swal from 'sweetalert';
import { API } from '../Api';
import { BarLoader } from '../common/BarLoader';

/**
 * Function GenerateFacebookTagsModal manages adding members' names with their Facebook IDs
 * to the list of the modal, with the text and title for the post that will be copied to the
 * clipboard and later to the FB post, together with the FB group ID.
 *
 * @property {filters: {endDate: string, excluded_member_ids: array,group_id: number,
 * is_multi_page_select_all: boolean,searchText: string, selected_member_ids: array<number>,
 * sort: string, startDate: string, tags: string}}
 * @property {closeModal} Function
 *
 * @returns {JSX.Element} modal instance.
 */
export class GenerateFacebookTagsModal extends Component {

    /**
     * Initialize states and properties for the component.
     *
     * @param {object} props 
     */
    constructor(props) {
        super(props);
        this.state = {
            /**
             * @type {array<{full_name: string, fb_id: number, id: number}>} collection of
             * members data that contains concatenated f_name and l_name as full_name.
             */
            members: [],

            /** @type {number} the FB ID of the group. */
            fbGroupId: null,

            /** @type {string} the title of the post for the FB group. */
            postTitle: '',

            /** @type {string} the text of the post for the FB group. */
            postText: '',

            /** @type {string} the status of the post creation on the FB group. */
            postCreationMessage: '',

            /** @type {string} the code of the status for post creation. */
            postCreationCode: null,

            /** @type {boolean} show loader while post is created. */
            showLoader: false,
        };

        /** @type {string} the ID of the textarea input. */
        this.textareaId = 'content-for-creating-post-automatically';

        /** @type {string} the type of the event, emitted from the extension. */
        this.postCreationStatus = 'postCreationStatus';

        /** @type {string} the codes for the certain type of the event's message. */
        this.MESSAGE_CODES = {
            SUCCESS: 200,
            WARNING: 500,
        }
    }

    /**
     * When the component is mounted, it calls the method for getting the
     * data for selected members from the backend, how they could be stored to
     * the local storage. It also calls the function that listens for MessageEvent
     * emitted with postMessage from the extension, about the status of the post
     * creation.
     *
     * @returns {void}
     */
    componentDidMount() {
        this.getMembersNames();
        this.checkPostMessages();
    }

    /**
     * Called immediately before a component is destroyed. Perform any necessary
     * cleanup in this method, such as cancelled network requests, or cleaning up
     * any DOM elements created in componentDidMount.
     */
    componentWillUnmount() {
        this.checkPostMessages('removeEventListener');
    }

    /**
     * Insert selected members into textarea field. It also sets the state for postText,
     * that can be changed later on.
     *
     * @returns {void}
     */
    insertNamesIntoTextarea() {
        const postTextTextarea = document.getElementById(this.textareaId);
        const names = this.state.members.map(member => member.full_name).join(', ');
        postTextTextarea.value = names;
        this.setState({ postText: names });
    }

    /**
     * The method will call the endpoint and populate members
     * with the data from the response.
     *
     * @returns {void}
     */
    async getMembersNames() {
        try {
            const response = await API.getMembersNames(this.props.filters);
            if (response.status !== this.MESSAGE_CODES.SUCCESS) {
                this.props.closeModal();
                return swal({
                    icon: 'warning',
                    title: 'Something went wrong',
                    text: 'There is no appropriate response for the selected members. Please try again. If the problem occurs again, contact our support.',
                });
            }

            this.setState({
                members: response.data.members,
                fbGroupId: response.data.fbGroupId,
            });

            this.insertNamesIntoTextarea();
        } catch (error) {
            console.log(error);
        }
    }

    /**
     * Filter members by full name. It may happens that someone by typing change the name of a member
     * in the textarea, and for that case, that member won't be tagged in the post.
     *
     * @returns {array} of filtered members whose names don't match the full name in the textarea.
     */
    filteredMembers() {
        return this.state.members.filter(member => this.state.postText.includes(member.full_name));
    }

    /**
     * Send values for members, postTitle and postTex to the extension
     * local storage via postMessage() method.
     *
     * @returns {void}
     */
    sendCreateNewPostMessage() {
        if (
            !document.getElementById(this.textareaId).value.length
            || !this.state.fbGroupId
        ) {
            return swal({
                title: 'Please select at least one group member to create a post.',
                icon: 'warning',
            });
        }

        window.postMessage({
            type: "createNewPostByExtension",
            post: JSON.stringify({
                postTitle: this.state.postTitle,
                members: JSON.stringify(this.filteredMembers()),
                postText: this.state.postText,
                fbGroupId: this.state.fbGroupId,
            })
        }, "*");

        this.setState({ showLoader: true });
    }

    /**
     * Show modal window as notification that describes the status of the post
     * creation on the FB group page.
     *
     * @returns {void}
     */
    showNotificationModal() {
        let icon = 'success';
        switch (this.state.postCreationCode) {
            case this.MESSAGE_CODES.WARNING:
                icon = 'warning';
                break;
        }

        swal({
            title: this.state.postCreationMessage,
            icon,
        })
            .then(() => {
                if (this.state.postCreationCode === this.MESSAGE_CODES.SUCCESS) {
                    this.props.closeModal();
                }
            });
    }

    /**
     * Adds or removes event listener for MessageEvent emitted with postMessage from the extension
     * and executes an appropriate action.
     *
     * @param {string} addEventListener add or remove event listener.
     *
     * @returns {void}
     */
    checkPostMessages(addEventListener = 'addEventListener') {
        window[addEventListener]('message', event => {
            if (event.data?.response?.type === this.postCreationStatus) {
                this.setState({
                    showLoader: false,
                    postCreationMessage: event.data.response.message,
                    postCreationCode: event.data.response.code,
                });
                this.showNotificationModal();
            }
        }, false);
    }

    /**
     * Set the value for the postTitle in the state with the value
     * from the input field of modal window.
     *
     * @param {string} postTitle title of the post.
     */
    handleSetPostTitle(postTitle) {
        this.setState({ postTitle: postTitle });
    }

    /**
     * Set the value for the postText in the state with the value
     * from the input field of modal window.
     *
     * @param {string} postText body of the post.
     */
    handleSetPostText(postText) {
        this.setState({ postText: postText });
    }

    render() {
        return (
            <Modal
                dialogClassName="modal-90w"
                show={true}
                onHide={this.props.closeModal}
                backdrop="static"
            >
                <Modal.Header closeButton>
                    <Modal.Title>GENERATE FACEBOOK TAGS - Members ({this.state.members.length})</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <div className="m-2">
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">
                                Post title
                            </label>
                            <div className="col-sm-9">
                                <input
                                    type="text"
                                    name="postTitle"
                                    className="form-control-plaintext"
                                    value={this.state.postTitle}
                                    onChange={e => this.handleSetPostTitle(e.target.value)}
                                    placeholder="Enter post title"
                                />
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">
                                Post text
                            </label>
                            <div className="col-sm-9">
                                <div className="react-tagsinput">
                                    <textarea
                                        style={{ fontSize: "1rem" }}
                                        name="postText"
                                        className="form-control-plaintext"
                                        rows="10"
                                        cols="50"
                                        value={this.state.postText}
                                        id={this.textareaId}
                                        onChange={e => this.handleSetPostText(e.target.value)}
                                        placeholder="Enter post text"
                                    >
                                    </textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </Modal.Body>
                <Modal.Footer className="footer-center">
                    <div className="row">
                        <div className="col-md-6">
                            <button
                                type="button"
                                className="btn btn-primary btn-block"
                                onClick={() => this.sendCreateNewPostMessage()}
                            >
                                Create post
                            </button>
                        </div>
                        <div className="col-md-6">
                            <button
                                type="button"
                                className="btn btn-light btn-block"
                                onClick={this.props.closeModal}
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </Modal.Footer>
                {
                    this.state.showLoader
                    &&
                    <BarLoader message="Please wait while the post is created in the Facebook group." />
                }
            </Modal>
        )
    }
}
