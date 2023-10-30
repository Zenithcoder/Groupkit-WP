import React, { Component } from 'react';
import { Button, UncontrolledPopover, PopoverBody } from 'reactstrap';
import moment from 'moment';
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";

import 'jquery-resizable-columns/dist/jquery.resizableColumns.css';
import 'bootstrap-table/dist/bootstrap-table.min.css';

import 'jquery-resizable-columns/dist/jquery.resizableColumns.min.js';
import 'bootstrap-table/dist/bootstrap-table.js';

/**
 * Used by bootstrap-table-resizable.min.js
 * @link https://github.com/wenzhixin/bootstrap-table/blob/develop/dist/extensions/resizable/bootstrap-table-resizable.js#L1362
 *
 * @type {{}}
 */
window.store = require('store');
import 'bootstrap-table/dist/extensions/resizable/bootstrap-table-resizable.min.js';


import Select from 'react-select';
import TagsInput from 'react-tagsinput'
import 'react-tagsinput/react-tagsinput.css'
import swal from 'sweetalert'
import {API} from './Api'
import Modal from 'react-bootstrap/Modal'
import BulkManageTagsModal from './Modals/BulkManageTagsModal';
import { isEqual, cloneDeep } from 'lodash-es';
import RecommendedTags from "./RecommendedTags";
import whenJobComplete from './AsyncJobs';
import { GenerateFacebookTagsModal } from './Modals/GenerateFacebookTagsModal';
import { InstallExtensionModal } from './Modals/InstallExtensionModal';

import betaTesters from '../data/beta-testers.json';

/**
 * Multi select value for removing selected group members
 *
 * @type {string}
 */
const REMOVE_SELECTED_MEMBERS = 'remove_selected_members';

/**
 * Multi select value for downloading group members csv action
 *
 * @type {string}
 */
const DOWNLOAD_GROUP_MEMBERS_CSV = 'download_group_members_csv';

/**
 * Multi select value for re-send to integration action
 *
 * @type {string}
 */
const RESEND_TO_INTEGRATION = 'resend_to_integration';

/**
 * Select members for the Facebook group that will be mentioned in the post.
 *
 * @type {string}
 */
 const GENERATE_FACEBOOK_TAGS = 'generate_facebook_tags';

/**
 * Add or remove tags to the members.
 *
 * @type {string}
 */
const ADD_REMOVE_TAGS = 'tags';

class Details extends Component{
    constructor(props) {
        super(props);

        window.thisDetails = this;
        window.setEditFormFunctions = {};

        /**
         * @property {{ value: string, label: string }} autoResponder
         * @property {?number} currentPage
         * @property {?Date} startDate
         * @property {?Date} endDate
         * @property {{id: number}} groupsDetails
         * @property {?number} pageLimit
         * @property {?string} searchText
         * @property {{sortName: string, sortOrder: string}} sort
         * @property {string[]} tags
         */
        this.state = {
            editForm: 0,
            groupsDetails: props.groupsDetails || [],
            selectedMember: {},
            selectRowProp:{
                mode: 'checkbox',
                columnWidth: '60px',
                onSelect: this.handleRowSelect.bind(this),
                onSelectAll: this.handleSelectAll.bind(this),
            },

            /**
             * @type {Set<int>} collection of currently selected member ids
             */
            selectedMemberIds: new Set(),

            /**
             * @type {Set<int>} collection of member ids that should be excluded from bulk action
             */
            excludedMemberIds: new Set(),

            /**
             * @type {boolean} whether the select all checkbox is checked on the current page
             */
            isSelectAllOnPage: false,

            /**
             * @type {boolean} flag used to activate bulk selection where all of the members available in the filtered selection
             * are considered as selected, except those specified in {@see excludedMemberIds}
             */
            isMultiPageSelectAll: false,

            startDate: null,
            endDate: null,
            tags:[],
            tagsList:[],
            autoResponder:{ value: 'all', label: 'Integration - All' },
            autoResponderValues:[
                { value: 'all', label: 'Integration - All' },
                { value: 'ADDED', label: 'Added' },
                { value: 'NOT_ADDED', label: 'Not Added' },
                { value: 'ERROR', label: 'Error' },
                { value: 'NO_EMAIL', label: 'No Email'},
            ],
            multiAction: null,
            actionOptions: [
                { value: REMOVE_SELECTED_MEMBERS, label: 'Remove Selected', isDisabled: false },
                { value: DOWNLOAD_GROUP_MEMBERS_CSV, label: 'Download Selected', isDisabled: false },
                { value: RESEND_TO_INTEGRATION, label: 'Re-Send To Integration', isDisabled: false },
                { value: ADD_REMOVE_TAGS, label: 'Add/Remove Tags', isDisabled: false },
                // TODO: uncomment this line after beta testing and remove part from componentDidMount().
                // { value: GENERATE_FACEBOOK_TAGS, label: 'Generate Facebook Tags', isDisabled: false },
            ],
            /**
             * Show/hides columns on UI: true - show, false - hide.
             */
            columnsVisibility: {
                id: false,
                name: true,
                date_added: true,
                email: true,
                profile_id: false,
                respond_status: true,
                Q1_answer: true,
                Q2_answer: true,
                Q3_answer: true,
                phone_number: true,
                notes: true,
                tags: true,
                approved_by: false,
                invited_by: false,
                lives_in: false,
                agreed_group_rules: false,
            },

            /**
             * Represents width of the columns
             */
            columnsWidth: {
                checkbox: '1.68',
                id: '4.49',
                name: '12.53',
                date_added: '8.77',
                email: '3.84',
                profile_id: '5.79',
                respond_status: '8.22',
                Q1_answer: '5.7',
                Q2_answer: '5.8',
                Q3_answer: '5.82',
                phone_number: '7.21',
                notes: '4.04',
                tags: '2.85',
                approved_by: '6.47',
                invited_by: '4.67',
                lives_in: '4.6',
                agreed_group_rules: '7.53',
            },

            /**
             * Represent table prefix for adding column width in the local storage
             */
            resizableTableId: null,
            showDetailsScreenLoader: 0,
            emailValidationError: null,
            livesInValidationError: null,
            selectedMemberEmail: '',
            currentPage:1,
            pageLimit: localStorage.hasOwnProperty('memberSizePerPage')
                ? parseInt(localStorage.getItem('memberSizePerPage'))
                : 10,
            searchText: '',
            sort: null,
            showBulkManageTagsModal: false,
            totalRecordsOnPage: 0, //store total number of records as per pagination step.
            table: null,
        };
    }

    async componentDidMount() {
        // TODO: for beta testing. Remove it after the testing is successful.
        if (betaTesters.includes(this.props.user.email)) {
            this.state.actionOptions.push(
                { value: GENERATE_FACEBOOK_TAGS, label: 'Generate Facebook Tags', isDisabled: false }
            );
        }
        window.addEventListener('message', async function (event) {
            if (event.data.type === 'refresh_data') {
                this.reloadMembers();
            }
        }.bind(this));

        this.setResizableTableId();
        const groupSettings = await this.getGroupSettings();
        this.setColumnsWidthOnLoad(groupSettings?.columns_width);
        this.loadTable();
        this.reloadMembers();
        this.refreshColumnsVisibility();
    }

    /**
     * Initializes the Bootstrap Table (https://bootstrap-table.com/) and adds drag scrolling to it
     */
    loadTable() {
        const $table = $('#table');
        this.setState({ table: $table });
        $(
            function () {
                $table.bootstrapTable(
                    {
                        /**
                         * Sets page size on the table load
                         */
                        pageSize: localStorage.hasOwnProperty('memberSizePerPage')
                            ? parseInt(localStorage.getItem('memberSizePerPage'))
                            : 10
                        ,
                        onPostBody:
                            /**
                             * After the table is rendered, selects the multi page selections and
                             * enables table drag scrolling if enabled
                             *
                             * @param {Array<Object>} members all members that match the current filters
                             */
                            function (members) {
                                let selectedIds = thisDetails.state.isMultiPageSelectAll
                                    ? members
                                        .map( ({ id }) => id )
                                        .filter(id => !thisDetails.state.excludedMemberIds.has(id))
                                    : Array.from(thisDetails.state.selectedMemberIds)
                                ;

                                $table.bootstrapTable('checkBy', { field: 'id', values: selectedIds });
                            }
                        ,
                        onColumnSwitch:
                            /**
                             * Persist the visible column changes
                             *
                             * @param {string} field the field identifier of the column whose visibility was toggled
                             * @param {boolean} isChecked true if the column should be displayed, otherwise false
                             */
                            function (field, isChecked) {
                                thisDetails.setColumnVisibility(
                                    thisDetails.getColumnKeyFromTableField(field),
                                    isChecked ? 'show' : 'hide'
                                );
                            }
                        ,
                        onPageChange:
                            /**
                             * Notifies the app that the state has changed for either the page size
                             * or the number members displayed per page, and signals it to refresh
                             * the data
                             *
                             * @param {Number} pageNumber the page number
                             * @param {Number} pageSize the page size
                             */
                            function (pageNumber, pageSize) {
                                localStorage.setItem('memberSizePerPage', pageSize);

                                thisDetails.refreshTable({
                                    currentPage: pageNumber,
                                    pageLimit: pageSize,
                                });
                            }
                        ,
                        onSearch:
                            /**
                             * Refreshes data according to the searched text
                             *
                             * @param {string} searchText The text of the search input
                             */
                            function (searchText) {
                                thisDetails.refreshTable({
                                    searchText: searchText,
                                    currentPage: 1,
                                });
                            }
                        ,
                        onCheck:
                            /**
                             * Handles tracking multipage-select when user checks a row
                             *
                             * @param {Object} row the record corresponding to the clicked row
                             * @param {Element} element the DOM element checked.
                             */
                            function (row, element) {
                                thisDetails.handleRowSelect(row, true);
                            }
                        ,
                        onUncheck:
                            /**
                             * Handles tracking multipage-select when user unchecks a row
                             *
                             * @param {Object} row the record corresponding to the clicked row
                             * @param {Element} element the DOM element unchecked.
                             */
                            function (row, element) {
                                thisDetails.handleRowSelect(row, false);
                            }
                        ,
                        onCheckAll:
                            /**
                             * Handles tracking multipage-select when user clicks the check-all checkbox
                             *
                             * @param {Array<Object>} rowsAfter array of records of the now checked rows.
                             * @param {Array<Object>} rowsBefore array of records of the checked rows before.
                             */
                            function (rowsAfter, rowsBefore) {
                                thisDetails.handleSelectAll(true, rowsAfter);
                            }
                        ,
                        onUncheckAll:
                            /**
                             * Handles tracking multipage-select when user clicks the uncheck-all checkbox
                             *
                             * @param {Array<Object>} rowsAfter array of records of the now checked rows.
                             * @param {Array<Object>} rowsBefore array of records of the checked rows before.
                             */
                            function (rowsAfter, rowsBefore) {
                                thisDetails.handleSelectAll(false, rowsBefore);
                            }
                        ,
                        onSort:
                            /**
                             * Redraws the table upon sorting a column
                             *
                             * @param {string} sortColumnName the sort column field name.
                             * @param {string} sortColumnOrder the sort column order.
                             */
                            function (sortColumnName, sortOrder) {
                                let sortName = sortColumnName == 'name' ? 'f_name' : sortColumnName;
                                thisDetails.refreshTable({sort: {sortName: sortName, sortOrder: sortOrder}});
                            }
                        ,
                        onResetView:
                            /**
                             * Adds resize event listener on all table headers
                             *
                             * @param {BootstrapTable} instance
                             */
                            function (instance) {
                                thisDetails.bindTableResizeListener(instance.$header.find('th:not(".bs-checkbox")'));
                            }
                        ,
                    }
                )
            }
        )
    }

    /**
     * Adds event listener to the provided table headers
     *
     * @param {Object} tableHeaders including headers for the desired table
     */
    bindTableResizeListener(tableHeaders) {
        if (!tableHeaders) {
            return; // if there is no provided table headers, stop function
        }

        const observer = new MutationObserver((mutationList) => {
            mutationList.forEach(function (mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const newColumnsWidth = thisDetails.getColumnsWidthFromLocalStorage();
                    const oldColumnsWidth = {};
                    Object.keys(thisDetails.state.columnsWidth).forEach(function (columnWidthName) {
                        if (Object.keys(newColumnsWidth).includes(columnWidthName)) {
                            oldColumnsWidth[columnWidthName] = thisDetails.state.columnsWidth[columnWidthName];
                        }
                    });

                    // If there is differences new and old columns we store new columns width values
                    if (!_.isEqual(oldColumnsWidth, newColumnsWidth)) {
                        thisDetails.setState({ columnsWidth: {...thisDetails.state.columnsWidth, ...newColumnsWidth} });
                        thisDetails.sendColumnsWidth(newColumnsWidth);
                    }
                }
            })
        });

        tableHeaders.each((index, header) => {
            observer.observe(header, {
                attributes: true,
                attributeFilter: ['style', 'class'],
            });
        });
    }

    /**
     * Gets columns width from local storage for current user and current group
     *
     * @returns {Object} containing columns width stored in the local storage for the current user and group
     */
    getColumnsWidthFromLocalStorage() {
        return Object.assign(
            {},
            ...Object.keys(localStorage)
                .filter((localStorageKey) => {
                    return localStorageKey.includes(this.state.resizableTableId)
                })
                .map(localStorageName => ({
                    [localStorageName.replace(this.state.resizableTableId + '-', '')]: localStorage.getItem(localStorageName)
                }))
        );
    }

    /**
     * Sets resizable table id by current user and current group
     */
    setResizableTableId() {
        this.setState({
            resizableTableId: 'table-group-' + this.props.groupsDetails.id + '-user-' + this.props.user.id,
        });
    }

    /**
     * Redraws the BootstrapTable according to the provided state
     *
     * @param {Object} state to be set to this Details component prior to redrawing the table
     */
    refreshTable(state) {
        this.setState(state);
        postMessage({ type: "refresh_data" }, "*");
    }

    /**
     * Retrieves the data for the Bootstrap table
     *
     * @returns {{total: Number, rows: Array}} set in the format accepted by BootstrapTable for pagination
     */
    getTableData() {
        return {
            total: this.props.membersFound,
            rows: this.state.groupsDetails.members,
        };
    }

    /**
     * Gets the names of all columns that should be visible in the rendered table
     *
     * @returns {string[]} visible columns given in no particular order
     */
    getVisibleColumns() {
        let visibleColumns = [];

        for (let key in this.state.columnsVisibility) {
            if (this.state.columnsVisibility[key]) { visibleColumns.push(key); }
        }

        return visibleColumns;
    }

    /**
     * Load members from server if filters are changed through state
     */
    componentWillUpdate (nextProps, nextState, nextContext) {
        const nextParams = this.getFilterParametersFromState(nextState);
        const allCurrentFilters = this.getFilterParametersFromState(this.state);
        if (!isEqual(nextParams, allCurrentFilters)) {
            this.props.filterMember(nextParams);
        }

        const currentFiltersWithoutPagination = this.getFiltersWithoutPagination(allCurrentFilters);
        const newFiltersWithoutPagination = this.getFiltersWithoutPagination(nextParams);

        if (!isEqual(currentFiltersWithoutPagination, newFiltersWithoutPagination)) {
            this.setState({ currentPage: 1 });
            this.clearSelection();
        }
    }

    /**
     * Returns filters without paginated properties from the provided filters
     *
     * @param {Object} filters including group_id, perPage, page, startDate, endDate, searchText, sort, tags params
     *
     * @return {Pick<*, Exclude<keyof *, "page"|"perPage">>}
     */
    getFiltersWithoutPagination(filters) {
        const {page, perPage, ...filtersWithoutPagination} = filters;

        return filtersWithoutPagination;
    }

    componentWillReceiveProps(props) {
        this.setState({groupsDetails:props.groupsDetails});

        if (props.groupsDetails.members) {
            this.setState({ totalRecordsOnPage: props.groupsDetails.members.length });
        }
    }
    // Edit Form
    setEditForm(member, parm) {
        this.setState({
            selectedMember: {
                ...cloneDeep(member),
                tags_to_add: [],
                tags_to_delete: [],
                recommended_tags_to_add: this.state.groupDetails?.recommended_tags ?? [],
                recommended_tags_to_delete: [],
            },
            editForm: parm,
            emailValidationError: null,
            livesInValidationError: null,
            selectedMemberEmail: member.email || '',
        })
    }

    handleChange(param,event){
        this.state.selectedMember[param] = event.target.value;
        this.setState({ selectedMember: this.state.selectedMember });
    }

    /**
     * Handles checkbox input changes for the provided parameter of the selected member
     *
     * @param {string} param is the selected field of the selected member for an update
     * @param {object} event object of the selected field
     *
     * @return {void}
     */
    handleCheckboxChange(param, event) {
        this.state.selectedMember[param] = event.target.checked ? 1 : 0;
        this.setState({ selectedMember: this.state.selectedMember });
    }

    validateMemberEmail(email) {
        const emailRegex = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        if (!emailRegex.test(String(email).toLowerCase())) {
            this.setState({ emailValidationError: 'Email address not valid' });
            return false;
        }
        return true;
    }

    /**
     * Validates lives in column for selected member before updating the member
     *
     * @param {string} livesIn field of the selected member
     *
     * @return {boolean} true if lives in is not greater than limit, otherwise false
     */
    validateMemberLivesIn(livesIn) {
        if (livesIn.length <= 255) {
            return true;
        }

        this.setState({ livesInValidationError: 'Maximum length for lives in field is 255 characters' });
        return false;
    }

    saveFormData(){
        if (
            (this.state.selectedMember.email && !this.validateMemberEmail(this.state.selectedMember.email))
            || !this.validateMemberLivesIn(this.state.selectedMember.lives_in)
        ) {
            return;
        }

        const editedMember = this.state.selectedMember;
        let sendToIntegration = false;

        if (
            this.state.selectedMemberEmail !== editedMember.email
            && this.groupHasIntegration()
            && !this.hasConnectedGoogleSheetIntegration()
        ) {
            editedMember['respond_status'] = 'Not Added';
        }

        let originalMember = this.props.groupsDetails.members.find((item) => {
            return item.id === editedMember.id;
        });

        if (
            this.groupHasIntegration()
            && this.isIntegrationFieldsChanged(editedMember, originalMember)
        ) {
            sendToIntegration = true;
        }

        API.updateMember(editedMember).then((res) => {
            if (res.data.code === 200) {
                this.setEditForm([], 0);
                //if updated member email then send to data in integration.
                if (sendToIntegration) {
                    this.sendToAutoresponderInBackground([editedMember.id]);
                    editedMember.respond_status = 'Processing';
                }
                this.updateGroupMember(editedMember);
            }
            swal(res.data.message);
        }).catch((error) => {
            swal('Invalid Request');
        });
    }

    /**
     * Check if integration fields changed for sending member data to the connected integration
     *
     * @param {object} editedMember containing new group member data after modification
     * @param {object} originalMember containing group member data before modification
     *
     * @return {boolean} true if proper fields are changed, otherwise false
     */
    isIntegrationFieldsChanged(editedMember, originalMember) {
        let changedField = originalMember.f_name !== editedMember.f_name
            || originalMember.l_name !== editedMember.l_name
            || originalMember.email !== editedMember.email
            || originalMember.a1 !== editedMember.a1
            || originalMember.a2 !== editedMember.a2
            || originalMember.a3 !== editedMember.a3;

        if (this.hasConnectedGoogleSheetIntegration() && !changedField) {
            return originalMember.lives_in !== editedMember.lives_in
                || originalMember.agreed_group_rules !== editedMember.agreed_group_rules
                || originalMember.notes !== editedMember.notes
                || !isEqual(originalMember.tags, editedMember.tags)
                || originalMember.phone_number !== editedMember.phone_number
            ;
        }

        return changedField;
    }

    /**
     * Update local group member after api call
     *
     * @param {Object} memberData information for the updated group user
     */
    updateGroupMember(memberData) {
        let groupDetails = this.state.groupsDetails;

        groupDetails.members = groupDetails.members.map((member) => {
            if (memberData.id === member.id) {
                member.f_name = memberData.f_name;
                member.l_name = memberData.l_name;
                member.email = memberData.email;
                member.notes = memberData.notes;
                member.tags = memberData.tags;
                member.a1 = memberData.a1;
                member.a2 = memberData.a2;
                member.a3 = memberData.a3;
                member.phone_number = memberData.phone_number;
                member.respond_status = memberData.respond_status;
                member.agreed_group_rules = memberData.agreed_group_rules;
            }

            return member;
        });

        if (memberData.recommended_tags_to_add.length) {
            memberData.recommended_tags_to_add.forEach(tag => {
                if (!groupDetails.recommended_tags.includes(tag)) {
                    groupDetails.recommended_tags.push(
                        {
                            label: tag,
                            is_recommended: 1,
                            group_id: groupDetails.id,
                        }
                    );
                }
            });
        }

        if (memberData.recommended_tags_to_delete.length) {
            groupDetails.recommended_tags = groupDetails.recommended_tags.filter(tag => {
                return !memberData.recommended_tags_to_delete.includes(tag.label);
            });
        }

        this.refreshTable({ groupsDetails: groupDetails });
    }

    /**
     * Adds or removes tags on {@see TagsInput} component change
     *
     * @param {array} tags that component has
     * @param {array} changedTags that are removed or added from component
     */
    handleChangeTags(tags, changedTags) {
        let selectedMember = Object.assign({}, this.state.selectedMember);
        const changedTag = changedTags[0]; // TagInput component always send single tag but as array

        if (
            tags.length > selectedMember.tags.length
            && !selectedMember.tags_to_add.includes(changedTag)
            && !selectedMember.tags.find(tag => tag.label === changedTag)
        ) {
            // Add new tab
            selectedMember.tags.push({ label: changedTag });
            this.state.selectedMember.tags_to_add.push(changedTag);
        } else if (tags.length < selectedMember.tags.length) {
            // Remove existing tag
            const deleteTag = selectedMember.tags.find((tag) => {
                return tag.label === changedTag;
            });

            const tagIndex = selectedMember.tags.findIndex((tag) => tag.label === deleteTag.label);
            selectedMember.tags.splice(tagIndex, 1);

            if (deleteTag.pivot) { // delete only tags that are stored in the database
                changedTags.forEach(tag => {
                    if (changedTag === deleteTag.label) {
                        this.state.selectedMember.tags_to_delete.push(changedTag);
                    }
                });
            }

            if (selectedMember.tags_to_add.includes(changedTag)) { // remove tag that is not stored in the database
                const addTagIndex = selectedMember.tags_to_add.findIndex((tag) => tag === changedTag);
                selectedMember.tags_to_add.splice(addTagIndex, 1);
            }
        }

        this.setState({ selectedMember: selectedMember });
    }

    /**
     * Adds provided tag to the selected member tags
     *
     * @param {String} tag that will be added to the current member tags
     */
    addTag(tag) {
        let tags = JSON.parse(JSON.stringify(this.state.selectedMember.tags));
        tags.push(tag);

        this.handleChangeTags(tags, [tag]);
    }

    /**
     * Adds provided tags to the group recommended tags via an editing group member
     *
     * @param {Array} tags that will be added as group recommended tags
     */
    addRecommendedTag(tags) {
        this.state.selectedMember.recommended_tags_to_add = tags;
    }

    /**
     * Removes a tag from a current group recommended tag via an editing group member
     *
     * @param {string} removedTag from the group recommended tags
     */
    removeRecommendedTag(removedTag) {
        this.state.selectedMember.recommended_tags_to_delete.push(removedTag);
    }

    /**
     * Removes all group recommended tags via an editing group member
     */
    removeAllRecommendedTags() {
        this.state.selectedMember.recommended_tags_to_delete = this.state.groupsDetails.recommended_tags.map(e => e.label);
    }

    editForm(){
        return(
            <Modal
                dialogClassName="modal-90w"
                show= {this.state.editForm ? true : false}
                onHide={this.setEditForm.bind(this, [], 0)}
                backdrop="static"
                keyboard={false}
            >
                <Modal.Header closeButton>
                    <Modal.Title>EDIT GROUP MEMBER DATA</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <form className="forms-sample m-2">
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">First Name</label>
                            <div className="col-sm-9">
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder="First Name"
                                    value={this.state.selectedMember.f_name || ''}
                                    onChange={this.handleChange.bind(this,"f_name")}
                                />
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">Last Name</label>
                            <div className="col-sm-9">
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder="Last Name"
                                    value={this.state.selectedMember.l_name || ''}
                                    onChange={this.handleChange.bind(this,"l_name")}
                                />
                            </div>
                        </div>
                        <div className="form-group row">
                            <label  className="col-sm-3 col-form-label">Email Address</label>
                            <div className="col-sm-9">
                                <div className="row">
                                    <div className="col">
                                        <input
                                            type="email"
                                            className='col form-control'
                                            placeholder="Email Address"
                                            value={this.state.selectedMember.email || ''}
                                            onChange={this.handleChange.bind(this, 'email')}
                                        />
                                    </div>
                                </div>
                                <div className="row">
                                    <small className="col col-form-label-sm text-danger" hidden={!this.state.emailValidationError}>
                                        {this.state.emailValidationError}
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">Q1 Answer</label>
                            <div className="col-sm-9">
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder="Q1 Answer"
                                    value={this.state.selectedMember.a1 || ''}
                                    onChange={this.handleChange.bind(this,"a1")}
                                />
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">Q2 Answer</label>
                            <div className="col-sm-9">
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder="Q2 Answer"
                                    value={this.state.selectedMember.a2 || ''}
                                    onChange={this.handleChange.bind(this,"a2")}
                                />
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">Q3 Answer</label>
                            <div className="col-sm-9">
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder="Q3 Answer"
                                    value={this.state.selectedMember.a3 || ''}
                                    onChange={this.handleChange.bind(this,"a3")}
                                />
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">Phone Number</label>
                            <div className="col-sm-9">
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder="Phone Number"
                                    value={this.state.selectedMember.phone_number || ''}
                                    onChange={this.handleChange.bind(this,"phone_number")}
                                />
                            </div>
                        </div>
                        <div className="form-group row notes">
                            <label className="col-sm-3 col-form-label">Notes</label>
                            <div className="col-sm-9">
                                <textarea
                                    row="10"
                                    className="form-control"
                                    placeholder="Notes"
                                    value={this.state.selectedMember.notes || ''}
                                    onChange={this.handleChange.bind(this,"notes")}
                                />
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">Lives in</label>
                            <div className="col-sm-9">
                                <div className="row">
                                    <div className="col">
                                        <input
                                            type="text"
                                            className='col form-control'
                                            placeholder="Lives in"
                                            value={this.state.selectedMember.lives_in || ''}
                                            onChange={this.handleChange.bind(this, 'lives_in')}
                                        />
                                    </div>
                                </div>
                                <div className="row">
                                    <small
                                        className="col col-form-label-sm text-danger"
                                        hidden={!this.state.livesInValidationError}
                                    >
                                        {this.state.livesInValidationError}
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">Tags</label>
                            <div className="col-sm-9">
                                <TagsInput
                                    placeholder="Tags"
                                    value={
                                        this.state.selectedMember.tags
                                            ? this.state.selectedMember.tags.map((tag) => tag.label)
                                            : JSON.parse('[]')
                                    }
                                    onChange={this.handleChangeTags.bind(this)}
                                />
                            </div>
                        </div>
                        {
                            <RecommendedTags
                                tags={this.state.groupsDetails.recommended_tags}
                                addTag={this.addRecommendedTag.bind(this)}
                                onTagClick={this.addTag.bind(this)}
                                removeTag={this.removeRecommendedTag.bind(this)}
                                removeTags={this.removeAllRecommendedTags.bind(this)}
                            />
                        }
                        <div className="form-group row">
                            <label className="col-sm-3 col-form-label">Agreed To Group Rules</label>
                            <div className="col-sm-9">
                                <small className="form-control">
                                    <input
                                        type="checkbox"
                                        checked={this.state.selectedMember.agreed_group_rules}
                                        onChange={this.handleCheckboxChange.bind(this, 'agreed_group_rules')}
                                    />
                                </small>
                            </div>
                        </div>
                    </form>
                </Modal.Body>
                <Modal.Footer className="footer-center">
                    <button type="button" className="btn btn-primary" onClick={this.saveFormData.bind(this)}>Save</button>
                    <button type="button" className="btn btn-light" onClick={this.setEditForm.bind(this,[],0)}>Close</button>
                </Modal.Footer>
            </Modal>
        )
    }

    /**
     * Resets filters to default values
     */
    clearFilters() {
        this.clearSelection();
        this.setState({
            startDate: null,
            endDate: null,
            tags: [],
            autoResponder: {
                value: 'all',
                label: 'Integration - All',
            },
        });

        this.setState({ filter: 0 });
        this.getGroupsTagList();
    }

    /**
     * Resets search to default value
     */
    clearSearch() {
        this.setState({ searchText: '' });
        this.state.table.bootstrapTable('resetSearch');
    }

    /**
     * Resets fields affecting the selection process
     * Empties selected and excluded member ids, sets {@see isSelectAllOnPage} and {@see isMultiPageSelectAll} to false
     */
    clearSelection() {
        this.state.selectedMemberIds.clear();
        this.state.excludedMemberIds.clear();
        this.setState({
            selectedMemberIds: this.state.selectedMemberIds,
            excludedMemberIds: this.state.excludedMemberIds,
            isSelectAllOnPage: false,
            isMultiPageSelectAll: false,
        });
    }

    /**
     * Reset the UI to the default vales.
     *
     * @return {void}
     */
    resetPageToDefault() {
        this.clearSelection();
        this.clearSearch();
        this.clearFilters();
    }

    /**
     * Handles redrawing the table after date filter changes
     *
     * @param {string} dateBoundary either 'startDate' or 'endDate'
     * @param {string} date The date to which the bound is set
     */
    handleChangeDate(dateBoundary,date){
        this.refreshTable({ [dateBoundary]: date });
    };

    /**
     * Handles redrawing the table after tag filter changes
     *
     * @param {Array<string>} tags a list of tags by which to filter
     */
    handleFilterTags(tags){
        this.refreshTable({ tags: tags });
    }

    /**
     * Handles redrawing the table after an integration results filter change
     *
     * @param {Object} result The result of an integration attempt
     */
    selectAutoResponder(result){
        this.refreshTable({ autoResponder: result });
    }

    /**
     * Indicates that provided feature/function support bulk action
     *
     * @param {String} feature to compare is it compatible with bulk action
     *
     * @return {boolean} true if support bulk action or bulk action is not activated, otherwise false
     */
    isSupportedBulkAction(feature) {
        const supportedFeatures = new Set([
            REMOVE_SELECTED_MEMBERS,
            DOWNLOAD_GROUP_MEMBERS_CSV,
            ADD_REMOVE_TAGS,
            GENERATE_FACEBOOK_TAGS,
        ]);

        if (supportedFeatures.has(feature)) {
            return true;
        }

        if (feature === RESEND_TO_INTEGRATION && !this.groupHasIntegration()) {
            return false;
        }

        // temporary fallback to prevent all actions that are not explicitly declared to support cross-pagination selections
        return !this.isCrossPageSelection();
    }

    /**
     * Determines if the current Facebook group has connected integration
     *
     * @return {boolean} true if is connected to the integration, otherwise false
     */
    groupHasIntegration() {
        return !!this.state.groupsDetails.responder.length;
    }

    /**
     * Get the current selection of Ids from the group members
     *
     * @return {array} Ids of the selected group members
     */
    getSelectionIds(){
        const $table = $('#table');
        const selections = $table.bootstrapTable('getSelections');
        return selections.map(s=>s.id);
    }

    selectMultiAction(event){
        this.setState({ multiAction: event });

        switch (event.value) {
            case RESEND_TO_INTEGRATION:
                this.sendAutoresponder();
                break;
            case REMOVE_SELECTED_MEMBERS:
                this.removeMember();
                break;
            case DOWNLOAD_GROUP_MEMBERS_CSV:
                this.buildMembersCSV();
                break;
            case ADD_REMOVE_TAGS:
                this.showManageBulkTagsModal();
                break;
            case GENERATE_FACEBOOK_TAGS:
                this.showGenerateFacebookTagsModal();
                break;
        }
    }

    /**
     * Shows {@see BulkManageTagsModal} component
     */
    showManageBulkTagsModal() {
        this.setState({ showBulkManageTagsModal: true });
    }

    /**
     * Closes {@see BulkManageTagsModal} component
     * Sets multi action drop-down value to default
     */
    closeBulkManageTagsModal() {
        this.setState({ showBulkManageTagsModal: false });
        this.unselectSelectedRows();
        this.setBulkOptionDefaultValue();
    }

    /**
     * the dropdown Bulk Options/Select actions set default value
     */
    setBulkOptionDefaultValue() {
        this.setState({ multiAction: null });
    }

    /**
     * Disables any bulk action option from the action drop-down that is not supported
     * This happens automatically for all options if
     * 1. no group members are selected or
     * 2. the action is for resending to integrations and there are no integrations added
     * Otherwise it checks whether a cross pagination selection of group members has been requested and
     * whether that action is currently supported.
     */
    disableUnsupportedBulkActions() {
        const selectOptions = this.state.actionOptions.map(option => ({
            ...option,
            isDisabled: this.noMembersAreSelected() || !this.isSupportedBulkAction(option.value),
        }));

        this.setState({ actionOptions: selectOptions });
    }

    /**
     * Unselect selected rows on the table
     */
    unselectSelectedRows() {
        this.setState({
            selectRowProp: Object.assign({}, this.state.selectRowProp, { selected: [] }),
        });
    }

    /**
     * Handles when select all rows checkbox is clicked in the Bootstrap Table component
     *
     * @param {boolean} isSelected flag signifying whether select all checkbox has been checked or unchecked
     * @param {Array<Object>} rows table rows that have been selected/unselected, essentially complete current page
     * @param {int} rows[].id for the purpose of tracking selected members we are only interested in their ids
     */
    handleSelectAll (isSelected, rows) {
        rows.forEach(({ id }) => this.toggleMemberSelectById(id, isSelected));
        this.setState({
            isSelectAllOnPage: isSelected,
            selectedMemberIds: this.state.selectedMemberIds,
            excludedMemberIds: this.state.excludedMemberIds,
        });
    }

    /**
     * Handles when a selection checkbox is clicked in the Bootstrap Table component
     * {@link http://allenfang.github.io/react-bootstrap-table/docs.html#onSelect}
     *
     * @param {Object} row member that has been selected or unselected, depending on isSelected param
     * @param {boolean} isSelected whether a member has been selected or unselected
     * @param {int} row.id for the purpose of tracking selected members we are only interested in their ids
     */
    handleRowSelect(row, isSelected) {
        this.toggleMemberSelectById(row.id, isSelected);
        this.setState({
            selectedMemberIds: this.state.selectedMemberIds,
            excludedMemberIds: this.state.excludedMemberIds,
            isSelectAllOnPage: isSelected ? this.state.isSelectAllOnPage : false,
        });
    }

    /**
     * If 'multi page selection mode' is active (isMultiPageSelectAll is true)
     * adds or removes the provided id from excludedMemberIds depending on the provided flag
     * If it is inactive, adds or removes the provided id from the selectedMemberIds
     *
     * @param {int} id of the selected or unselected member
     * @param {boolean} isSelected whether the member with the provided id has been selected or unselected
     */
    toggleMemberSelectById (id, isSelected) {
        if (this.state.isMultiPageSelectAll) {
            if (isSelected) {
                this.state.excludedMemberIds.delete(id);
            } else {
                this.state.excludedMemberIds.add(id);
            }
        } else {
            if (isSelected) {
                this.state.selectedMemberIds.add(id);
            } else {
                this.state.selectedMemberIds.delete(id);
            }
        }
    }

    /**
     * Handles columns visibility when a user chooses
     * columns visibility in UI.
     *
     * @param {string} column key of the column in {@see columnsVisibility}.
     * @param {string} visibility if "show" the column will be visible, otherwise false.
     *
     * @returns {void}
     */
    setColumnVisibility(column, visibility) {
        const columnsVisibility = this.state.columnsVisibility;

        columnsVisibility[column] = (visibility === 'show');

        this.persistColumnsVisibility();
    }

    /**
     * Calls 'setColumnsVisibility' endpoint to store the data about columns visibility
     * into the local state and the local storage.
     *
     * @returns {void}
     */
    persistColumnsVisibility() {
        const groupId = this.props.groupsDetails.id;
        const columnsVisibility = this.state.columnsVisibility;
        const args = {columnsVisibility, groupId};

        this.setColumnsVisibility({
            user_id: this.props.user.id,
            group_id: groupId,
            columns_visibility: columnsVisibility,
        });

        API.setColumnsVisibility(args)
            .then(res => {
                if (res.status !== 200) {
                    swal({
                        text: 'Something went wrong with storing the data about columns visibility.',
                        icon: 'warning',
                        dangerMode: true,
                    });
                }
            });
    }

    /**
     * Get the status of columns visibility from the endpoint '/getColumnsVisibility/groupId'.
     *
     * @returns {void}
     */
    refreshColumnsVisibility() {
        const groupId = this.props.groupsDetails.id;

        API.getColumnsVisibility(groupId)
            .then(res => {
                if (res.data.data && res.status === 200) {
                    const columnsVisibility = res.data.data.columns_visibility;
                    columnsVisibility.columns_visibility = JSON.parse(columnsVisibility.columns_visibility)
                    this.setColumnsVisibility(columnsVisibility)
                } else {
                    this.persistColumnsVisibility();
                }
            });
    }

    /**
     * Sets columnsWidth on the component load if
     * 1. columns width is not null or empty
     * 2. columns width from the API are different from columns width from localstorage
     *
     * @param {JSON} columnsWidth containing all stored columns width in the database
     */
    setColumnsWidthOnLoad(columnsWidth) {
        if (!columnsWidth || columnsWidth === 'null') {
            return;
        }

        const apiColumnsWidth = JSON.parse(columnsWidth)

        if (!_.isEqual(apiColumnsWidth, this.getColumnsWidthFromLocalStorage())) {
            this.setState({ columnsWidth: apiColumnsWidth });
        }
    }

    /**
     * Sends columns width to the API in the background
     *
     * @param {Object} columnsWidth including all the columns width presented in the local storage
     */
    sendColumnsWidth(columnsWidth) {
        API.setColumnsWidth({ columnsWidth: columnsWidth, groupId: this.props.groupsDetails.id });
    }

   /**
     * Returns group settings from {@see API.getGroupSettings} API for the current group and current user
     *
     * @returns {Object} including group settings (columns visibility, columns width)
     */
    async getGroupSettings() {
        return await API.getGroupSettings(this.props.groupsDetails.id).then(res => {
            if (res.status === 200 && res.data.group_settings && res.data.group_settings.group_settings) {
                return res.data.group_settings.group_settings;
            }
        });
    }

    /**
     * Sets the local state with the data from the DB.
     *
     * @param {{ user_id: number, group_id: number, columns_visibility: string }} columnsVisibility
     *
     * @returns {void}
     */
    setColumnsVisibility(columnsVisibility) {
        this.setState({ columnsVisibility: columnsVisibility.columns_visibility });

        for (let columnKey in this.state.columnsVisibility) {
            if (this.state.columnsVisibility[columnKey]) {
                this.state.table.bootstrapTable('showColumn', this.getTableFieldFromColumnKey(columnKey));
            } else {
                this.state.table.bootstrapTable('hideColumn', this.getTableFieldFromColumnKey(columnKey));
            }
        }
    }

    /**
     * Get the table field by column name
     *
     * @param {string} columnKey is the key of the column defined in {@see columnsVisibility}
     *
     * @returns {string} the mapped table field identifier if it exists, otherwise the columnKey
     */
    getTableFieldFromColumnKey(columnKey) {
        const columnKeyToFieldMap = {
            date_added: 'date_add_time',
            profile_id: 'fb_id',
            Q1_answer: 'a1',
            Q2_answer: 'a2',
            Q3_answer: 'a3',
        };

        return columnKeyToFieldMap[columnKey] ?? columnKey;
    }

    /**
     * Get the table field by column name
     *
     * @param {string} field is the table field value for a column
     *
     * @returns {string} the mapped table column if it exists for {@see columnsVisibility}, otherwise the field value
     */
    getColumnKeyFromTableField(field) {
        const fieldToColumnKeyMap = {
            date_add_time: 'date_added',
            fb_id: 'profile_id',
            a1: 'Q1_answer',
            a2: 'Q2_answer',
            a3: 'Q3_answer',
        };

        return fieldToColumnKeyMap[field] ?? field;
    }

    /**
     * Deletes group members based on current selection via AJAX
     * If {@see isMultiPageSelectAll} flag is set to false, members in {@see selectedMemberIds} are deleted
     * otherwise all members in group are deleted, except those specified in {@see excludedMemberIds}
     *
     * @return {Promise<void>} resolved after members are deleted
     */
    removeMember () {
        return new Promise((resolve, reject) => {
            if (this.noMembersAreSelected()) {
                swal({ text:`Please select members.`, buttons: true, })
                    .then(this.setBulkOptionDefaultValue.bind(this));
                reject();
                return;
            }

            swal({
                text: 'Are you sure you want to do this?',
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then((shouldDelete) => {
                if (shouldDelete) {
                    API.removeGroupMember({
                        group_id: this.state.groupsDetails.id,
                        selected_member_ids: Array.from(this.getSelectionIds()),
                        excluded_member_ids: Array.from(this.state.excludedMemberIds),
                        is_multi_page_select_all: this.state.isMultiPageSelectAll,
                        ...this.getFilterParametersFromState(this.state),
                    })
                        .then(({ data: { async = false, job_id = null, message = '', async_message = '' }, status }) => {
                            if (async) {
                                whenJobComplete(job_id)
                                    .then(() => {
                                        swal(async_message, { icon: 'success' });
                                        resolve();
                                        this.reloadMembers();
                                    });
                            } else {
                                resolve();
                                this.reloadMembers();
                            }
                            swal(message, { icon: 200 === status ? 'success' : 'error' });
                            this.resetPageToDefault();
                        })
                        .catch(reject);
                    this.setBulkOptionDefaultValue();
                }
            });
        });
    }

    /** send Autoresponder  */
    async sendAutoresponder() {
        if (!this.groupHasIntegration()) {
            return swal(`There are no integrations configured yet. Configure an integration and try again.`)
                .then(() => this.setBulkOptionDefaultValue());
        }

        this.setState({ showDetailsScreenLoader: 1 });

        const response = await this.sendToIntegration({
            'group_members_id': Array.from(this.getSelectionIds()),
        });
        this.setState({ showDetailsScreenLoader: 0 });
        swal({
            text: response.data.message,
            icon: (response.status === 200 ? 'success' : ''),
            buttons: true,
        });

        this.clearSelection();
        this.reloadMembers();
        this.setBulkOptionDefaultValue();
    }

    /**
     * Sends group member to the connected integration in the background
     *
     * @param {int[]} memberIds containing one of more group member ids to be sent to the integration
     */
    sendToAutoresponderInBackground(memberIds) {
        if (!this.groupHasIntegration() || !memberIds.length) {
            return;
        }

        this.sendToIntegration({ 'group_members_id': memberIds }).then(() => {}).catch(() => {});
    }

    /**
     * Send the group id and group members id to integration API
     *
     * @param {Object} $requestObject containing group members id
     */
    sendToIntegration($requestObject) {
        return new Promise(resolve => {
            API.sendToIntegrationApi($requestObject).then((res) => {
                resolve(res);
            }).catch((error) => {
                resolve(error);
            });
        })
    }

    /**
     * Downloads selected group members csv based on current selection via AJAX
     * If {@see isMultiPageSelectAll} flag is set to false, members in {@see selectedMemberIds} are downloaded
     * otherwise all members in group are downloaded, except those specified in {@see excludedMemberIds}
     */
    buildMembersCSV() {
        swal('We are building a CSV file for you. You will need to wait depending on the number of group members.');

        API.buildGroupMembersCsvFile({
            group_id: this.state.groupsDetails.id,
            selected_member_ids: Array.from(this.getSelectionIds()),
            excluded_member_ids: Array.from(this.state.excludedMemberIds),
            is_multi_page_select_all: this.state.isMultiPageSelectAll,
            ...this.getFiltersWithoutPagination(
                this.getFilterParametersFromState(this.state)
            ),
        })
            .then((response) => {
                if (response.status !== 200) {
                    swal(response.response.data.message, { icon: 'error' });
                    return;
                }

                /** @todo fix {@see API.CurlCall} to not gobble exceptions, then move this to argument destructor */
                let { data: { async = false, job_id = null, message = '', file_name = '' } = {}, status } = response;

                if (async) {
                    whenJobComplete(job_id).then(() => {
                        this.props.onBuildMembersCSV(file_name);
                    });
                } else {
                    this.props.onBuildMembersCSV(file_name);
                }

                this.resetPageToDefault();
                this.setBulkOptionDefaultValue();
            });
    }

    /** Get Groups Tag */
    getGroupsTagList(){
        API.getGroupsTag(this.state.groupsDetails.id).then((res) => {
            if (res.data.code === 200) {
                const tagList = res.data.data.map((tag) => {
                    return {
                        label: tag.label,
                        value: tag.id,
                    };
                });

                this.refreshTable({ tagsList: tagList });
            }
        }).catch((error) => {
            //console.log(error)
        });
    }

    /**
     * Updates the members displayed taking into account the current filters
     */
    reloadMembers() {
        this.props.filterMember(this.getFilterParametersFromState(this.state))
            .then(() => {
                const numberOfPagesAvailable = Math.ceil(this.props.membersFound / this.state.pageLimit);
                if (numberOfPagesAvailable < this.state.currentPage) {
                    this.setState({ currentPage: 1 });
                }

                this.state.table.bootstrapTable('load', this.getTableData());
            })
            .catch(error => {
                swal(
                    `Something went wrong. The current page is not set after members reloading.`,
                    {icon: "error",}
                );
            });
    }

    /**
     * Collects and transforms filter from the provided state object
     * used for retrieving members from the API
     *
     * @param {Details.state} state
     *
     * @return {{ group_id: number, startDate: string, endDate: string, tags: string, autoResponder: string,
     * page: number, perPage: number, searchText: string, sort: {sortName: string, sortOrder: string} }}
     */
    getFilterParametersFromState ({
      autoResponder,
      currentPage,
      startDate,
      endDate,
      groupsDetails,
      pageLimit,
      searchText,
      sort,
      tags
    }) {
        return {
            group_id: groupsDetails.id,
            startDate: startDate ? moment(startDate).format('YYYY-MM-DD') : '',
            endDate: endDate ? moment(endDate).format('YYYY-MM-DD') : '',
            tags: tags ? tags.map((item) => item.value).join(',') : '',
            autoResponder: autoResponder.value ?? '',
            page: currentPage,
            perPage: pageLimit,
            searchText: searchText,
            sort: sort,
        };
    }

    /**
     * Collects and transforms filter from the provided state object
     * used for retrieving members and tags from the API.
     *
     * @return {{ group_id: number, startDate: string, endDate: string,
     * tags: string, autoResponder: string, searchText: string,
     * sort: {sortName: string, sortOrder: string}, selected_member_ids: Set,
     * excluded_member_ids: Set, is_multi_page_select_all: boolean }}
     */
    getFiltersForBulkManageTagsModal() {
        return {
            group_id: this.state.groupsDetails.id,
            startDate: this.state.startDate ? moment(this.state.startDate).format('YYYY-MM-DD') : '',
            endDate: this.state.endDate ? moment(this.state.endDate).format('YYYY-MM-DD') : '',
            tags: this.state.tags ? this.state.tags.map((item) => item.value).join(',') : '',
            autoResponder: this.state.autoResponder.value ?? '',
            searchText: this.state.searchText,
            sort: this.state.sort,
            selected_member_ids: Array.from(this.state.selectedMemberIds),
            excluded_member_ids: Array.from(this.state.excludedMemberIds),
            is_multi_page_select_all: this.state.isMultiPageSelectAll,
        };
    }

    /**
     * Returns count of selected group members.
     * If {@see isMultiPageSelectAll} selected,
     * returns distinction of {@see membersFound} and {@see excludedMemberIds} count,
     * otherwise returns {@see selectedMemberIds} count
     *
     * @return {number} of selected group members
     */
    getSelectedMembersCount() {
        return this.state.isMultiPageSelectAll
            ? this.props.membersFound - this.state.excludedMemberIds.size
            : this.state.selectedMemberIds.size;
    }

    /**
     * Determines if the group has connected GoogleSheet integration
     *
     * @return {boolean} true if the group has connected to the GoogleSheet integration, otherwise false
     */
    hasConnectedGoogleSheetIntegration() {
        return !!this.state.groupsDetails.responder.find(responder => responder.responder_type === 'GoogleSheet');
    }

    filter() {
        return(
            <UncontrolledPopover trigger="legacy" placement="right" target="PopoverLegacy">
                <PopoverBody>
                    <div className="modal-body">
                        <div className="form-group row">
                            <label className="col-12 p-0">Added From:</label>
                            <div className="col-12 p-0">
                                <DatePicker
                                    className="form-control"
                                    selected={this.state.startDate}
                                    onChange={this.handleChangeDate.bind(this,'startDate')}
                                    placeholder="MM/DD/YYYY"
                                />
                            </div>
                            <label className="col-12 text-left p-0 top_margin">To:</label>
                            <div className="col-12 p-0">
                                <DatePicker
                                    className="form-control"
                                    selected={this.state.endDate}
                                    onChange={this.handleChangeDate.bind(this,'endDate')}
                                    placeholder="MM/DD/YYYY"
                                />
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-12 p-0">Select Tags:</label>
                            {/*<input type="text" className="form-control" placeholder="Tags"/>*/}
                            <div className="col-12 p-0">
                                {/* <TagsInput value={ this.state.tags } onChange={this.handleFilterTags.bind(this)} /> */}
                                <Select
                                    isMulti={true}
                                    value={this.state.tags}
                                    onChange={this.handleFilterTags.bind(this)}
                                    options= {this.state.tagsList}
                                />
                            </div>
                        </div>
                        <div className="form-group row">
                            <label className="col-12 p-0">Integration:</label>
                            <Select
                                className="col-12 p-0"
                                value={this.state.autoResponder}
                                onChange={this.selectAutoResponder.bind(this)}
                                options={this.state.autoResponderValues}
                            />
                        </div>
                    </div>
                    <div className="modal-footer text-center" style={{'display':'inherit'}}>
                        <button type="button" className="btn btn-light" onClick={this.clearFilters.bind(this)}>Reset</button>
                    </div>
                </PopoverBody>
            </UncontrolledPopover>
        )
    }
    Deletegroup(){
        swal({
            text: "Are you sure you want to do this?",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                API.deleteGroup(this.state.groupsDetails.id).then((res) => {
                    if(res.data.code==200){
                        swal("Your group has been removed.",{
                            icon: "success",
                        });
                        this.redirectToHomePage();
                    }
                }).catch((error) => {
                    //console.log(error)
                });
            }
        });
    }

    /**
     * Renders the name column of the table of members
     *
     * @param {string} value the name of the
     * @param {Object} row All the data in the rows
     *
     * @returns {string} HTML for the name cell for the member
     */
    renderNameCell(value, row){
        setEditFormFunctions[row.id] = this.setEditForm.bind(this, row, 1);

        return `
            <div class="row">
                <div class="col-4 memberActionPin">
                    <i class="mdi mdi-account-edit cursor-pointer edit" onClick="setEditFormFunctions['${row.id}']();"></i>
                    <a class="cursor-pointer" target="_blank" href="https://www.messenger.com/t/${row.fb_id}">
                        <img
                            src="${row.img ?? 'https://graph.facebook.com/' + row.fb_id + '/picture?type=large'}"
                            class="img-sm rounded-circle"
                            alt=""
                        />
                    </a>
                </div>
                <div class="col-8 text-left p-0 member-name">
                    <a class="cursor-pointer" target="_blank" href="https://www.messenger.com/t/${row.fb_id}">
                        <label class="p-2 fname cursor-pointer">${row.f_name + ' ' + row.l_name}</label>
                    </a>
                </div>
            </div>
        `;
    }

    /**
     * Renders a 'Yes' or 'No' value for boolean cell values
     *
     * @param {boolean} value stating whether an affirmative answer is required
     * @param {Object} row All the data in the row
     *
     * @returns {string} response of 'Yes' for truthy otherwise 'No'
     */
    renderYesOrNoCell(value, row){
        return value ? 'Yes' : 'No';
    }

    /**
     * Renders the name of the approving member
     *
     * @param {Object} approver Object representing the approving member
     * @param {Object} row All the data in the row
     *
     * @returns {string} the name of the approving member when available, otherwise an empty string
     */
    renderApprovedByCell(approver, row){
        return approver.approved_by?.name ?? '';
    }

    /**
     * Renders the tags for the member
     *
     * @param {Array<Object>} tags all tags associated with this member
     * @param row All the data in the row
     *
     * @returns {string} The HTML of tags for the member
     */
    renderTagsCell(tags, row) {
        if (!tags.length) return '';

        let tagsHtml = '';

        tags.map(
            (tag, index) => {
                tagsHtml += `<span key="${index}" class="react-tagsinput-tag float-left">${tag.label}</span>`;
            }
        )

        return `<div>${tagsHtml}</div>`;
    }

    /**
     * Redirects to the home page using full page reload
     */
    redirectToHomePage() {
        window.location.href = window.base_url;
    }

    /**
     * Determines whether group members have been selected on more than one page due to pagination
     *
     * @returns {boolean} true if there is a possibility that group members have been selected on multiple pages,
     *                    otherwise false.
     */
    isCrossPageSelection() {
        return this.state.isMultiPageSelectAll || (this.state.selectedMemberIds.size > this.state.pageLimit);
    }

    /**
     * Determines whether or not any group members are currently selected
     *
     * @returns {boolean} True if no members are selected, otherwise false
     */
    noMembersAreSelected() {
        return !this.state.isMultiPageSelectAll && this.getSelectionIds().length === 0;
    }

    /**
     * Toggles bulk selection mode, depending on the provided isMultiPageSelectAll flag
     *
     * @param {boolean} isMultiPageSelectAll whether to turn the bulk selection mode on or off
     */
    toggleSelectAllInGroup(isMultiPageSelectAll) {
        this.state.selectedMemberIds.clear();
        this.state.excludedMemberIds.clear();

        if (isMultiPageSelectAll) {
            this.state.table.bootstrapTable('checkAll');
        } else {
            this.state.table.bootstrapTable('uncheckAll');
        }

        this.setState({
            isMultiPageSelectAll: isMultiPageSelectAll,
            isSelectAllOnPage: false,
            selectedMemberIds: this.state.selectedMemberIds,
            excludedMemberIds: this.state.excludedMemberIds,
        });
    }

    /**
     * Returns toolbar used in Bulk selection
     *
     * @return {JSX.Element}
     */
    getSelectAllInGroupRow () {
        const membersCount = this.props.membersFound ?? 0;

        if (!membersCount) {
            return <div></div>; //wait for group members to be loaded
        }

        const areAllGroupMembersOnCurrentPageSelected = membersCount > 0
            && this.state.groupsDetails.members
                ?.filter(
                    ({ id }) => this.state.isMultiPageSelectAll
                        ? this.state.excludedMemberIds.has(id)
                        : !this.state.selectedMemberIds.has(id)
                ).length == 0;

        if (this.isCrossPageSelection()) {
            return <div className={'card-body select-all-in-group-row'}>
                <span>
                    {
                        (this.state.isMultiPageSelectAll && !this.state.excludedMemberIds.size)
                        || (!this.state.isMultiPageSelectAll && this.state.selectedMemberIds.size === membersCount)
                            ? 'All matching'
                            : this.state.isMultiPageSelectAll
                                ? `${membersCount - this.state.excludedMemberIds.size} of ${membersCount}`
                                : `${this.state.selectedMemberIds.size} of ${membersCount}`
                    } members in "{this.state.groupsDetails.fb_name}" are selected.
                </span>
                <a onClick={this.toggleSelectAllInGroup.bind(this, false)}>Clear selection</a>
            </div>;
        } else if (
            areAllGroupMembersOnCurrentPageSelected
            && this.state.selectedMemberIds.size === this.state.pageLimit
        ) {
            return <div className={'card-body select-all-in-group-row'}>
                <span>All {this.state.groupsDetails.members.length} group members on this page are selected.</span>
                <a onClick={this.toggleSelectAllInGroup.bind(this, true)}>
                    Select all matching group members in "{this.state.groupsDetails.fb_name}" for bulk actions
                </a>
                <span>or</span>
                <a onClick={this.toggleSelectAllInGroup.bind(this, false)}>Clear selection</a>
            </div>;
        } else if (this.state.selectedMemberIds.size > 0) {
            return <div className={'card-body select-all-in-group-row'}>
                <span>{`${this.state.selectedMemberIds.size} of ${membersCount}`} members are selected.</span>
                <a onClick={this.toggleSelectAllInGroup.bind(this, true)}>
                    Select all matching group members for bulk actions
                </a>
                <span>or</span>
                <a onClick={this.toggleSelectAllInGroup.bind(this, false)}>Clear selection</a>
            </div>;
        }
    }

    /**
     * Checks if the HTML element <groupkit> with the ID 'groupkit_cloud_store' exists
     * on the page. If it does exist, then the extension is installed, otherwise
     * the extension is not installed.
     *
     * @returns {boolean} true if the exension is installed, otherwise false.
     */
    extensionIsInstalled() {
        this.setState({ showInstallExtensionModal: !document.querySelector('#groupkit_cloud_store') });
        return !!document.querySelector('#groupkit_cloud_store');
    }

    /**
     * Sets the GenerateFacebookTagsModal modal to be visible.
     *
     * @returns {void}
     */
    showGenerateFacebookTagsModal() {
        if (this.extensionIsInstalled()) {
            this.setState({ showGenerateFacebookTagsModal: true });
        }
    }

    /**
     * Close the GenerateFacebookTagsModal, InstallExtensionModal modals,
     * and reset the page state to the default value.
     *
     * @returns {void}
     */
    closeGenerateFacebookTagsModal() {
        this.setState({ showInstallExtensionModal: false });
        this.setState({ showGenerateFacebookTagsModal: false });
        this.setBulkOptionDefaultValue();
        this.resetPageToDefault();
    }

    render() {
        /**
         * Minimum table column width needed to set the overall minimum table width that allows
         * for resizing.  This is because if the table width is too small for the number of
         * columns, resizing is not allowed.  This number is in pixels
         *
         * @type {number}
         */
        let minResizeColumnWidth = 175;

        window.renderNameCell = this.renderNameCell.bind(this);
        window.renderYesOrNoCell = this.renderYesOrNoCell.bind(this);
        window.renderApprovedByCell = this.renderApprovedByCell.bind(this);
        window.renderTagsCell = this.renderTagsCell.bind(this);

        return (
            <div className="container-fluid page-body-wrapper m-0 p-0">
                {this.filter()}
                {this.state.editForm ? this.editForm() : ''}
                {
                    this.state.showBulkManageTagsModal
                    &&
                    <BulkManageTagsModal
                        show={this.state.showBulkManageTagsModal}
                        hideModal={this.closeBulkManageTagsModal.bind(this)}
                        recommendedTags={this.state.groupsDetails.recommended_tags}
                        reloadMembers={this.reloadMembers.bind(this)}
                        filters={this.getFiltersForBulkManageTagsModal()}
                        resetPageToDefault={this.resetPageToDefault.bind(this)}
                        selectedMembersNumber={this.getSelectedMembersCount()}
                        hasConnectedGoogleSheetIntegration={this.hasConnectedGoogleSheetIntegration()}
                    />
                }

                {
                    this.state.showInstallExtensionModal
                    &&
                    <InstallExtensionModal
                        showInstallExtensionModal={this.state.showInstallExtensionModal}
                        closeGenerateFacebookTagsModal={this.closeGenerateFacebookTagsModal.bind(this)}
                    />
                }

                {
                    this.state.showGenerateFacebookTagsModal
                    &&
                    <GenerateFacebookTagsModal
                        closeModal={this.closeGenerateFacebookTagsModal.bind(this)}
                        filters={this.getFiltersForBulkManageTagsModal()}
                    />
                }

                <div className="main-panel">
                    <div className="content-wrapper my-5 details_screen">
                        <div className="row">
                            <a className="p-2 cursor-pointer text-decoration-none heading-color" href={window.base_url}>
                                <i className="mdi mdi-keyboard-backspace"></i> Go Back
                            </a>
                        </div>
                        <div className="row">
                            <div className="col-md-12 flex-column">
                                <div className={this.state.groupsDetails.responder.length ? 'card group-management-heading' : 'card'}>
                                    <div className="card-body">
                                        <div className="row">
                                            <div className="groupDetails col-sm-12 col-md-12 col-lg-12 mb-3">
                                                <h6>
                                                    { this.state.groupsDetails.fb_name !== undefined && this.state.groupsDetails.fb_name }
                                                </h6>
                                            </div>
                                            <div className="col-6">
                                                <p className="text-muted mt-3">
                                                    { this.props.membersFound ?? 0 } Member(s)
                                                </p>
                                            </div>
                                            {
                                                this.props.user.access_team && this.props.user.id === this.state.groupsDetails.user_id &&
                                                <div className="col-6 text-right">
                                                    <button className="btn btn-sm btn-primary remove_group" onClick={this.Deletegroup.bind(this)}>REMOVE GROUP</button>
                                                </div>
                                            }
                                        </div>
                                    </div>
                                </div>
                                {
                                    this.state.groupsDetails.responder.length ?
                                        <div className="card">
                                            <div className="card-body">
                                                <h5 className="m-0">
                                                    Integration{this.state.groupsDetails.responder.length > 1 ? 's': ''} connected:&nbsp;
                                                    {this.state.groupsDetails.responder.map(responder => responder.responder_type).join(', ')}
                                                </h5>
                                            </div>
                                        </div>
                                        : ''
                                }
                            </div>
                        </div>
                        <div className="row">
                            <div className="col-sm-12 col-md-12 col-lg-3 group_title">
                                <h4 className="card-title">Manage Your Group Members</h4>
                            </div>

                            <div className="member_filter col-sm-12 col-md-6 col-lg-2">
                                <Select
                                    isSearchable={false}
                                    className="selectpicker"
                                    placeholder="Select An Action..."
                                    value={this.state.multiAction}
                                    onChange={this.selectMultiAction.bind(this)}
                                    options={this.state.actionOptions}
                                    onMenuOpen={this.disableUnsupportedBulkActions.bind(this)}
                                />
                            </div>
                            <div className="popup_member member_filter col-sm-12 col-md-6 col-lg-2">
                                <Button onClick={this.getGroupsTagList.bind(this)} className="btn btn-sm btn-primary filter_btn" id="PopoverLegacy" type="button">Filter Members</Button>
                            </div>
                        </div>
                        <div className="row">
                            <div className="col-12">
                                <div className="card">
                                    {this.getSelectAllInGroupRow()}
                                    <div className="card-body details_tables member_table">
                                        <table id="table"
                                               data-id-field="id"
                                               data-resizable="true"
                                               data-resizable-columns-id={this.state.resizableTableId}
                                               data-show-columns="true"
                                               data-search="true"
                                               data-show-toggle="true"
                                               data-page-list="[10, 25, 50, 100]"
                                               data-side-pagination="server"
                                               data-pagination="true"
                                               style={{'minWidth': (this.getVisibleColumns().length * minResizeColumnWidth) + "px"}}
                                        >
                                            <thead>
                                                <tr>
                                                    <th
                                                        data-field="checkbox"
                                                        data-width={this.state.columnsWidth.checkbox}
                                                        data-width-unit="%"
                                                        data-resizable-column-id="checkbox"
                                                        data-checkbox="true"
                                                    ></th>
                                                    <th data-field="id"
                                                        data-width={this.state.columnsWidth.id}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.id}
                                                        data-resizable-column-id="id"
                                                    >
                                                        Id
                                                    </th>
                                                    <th data-field="name"
                                                        data-width={this.state.columnsWidth.name}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.name}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-formatter="renderNameCell"
                                                        data-resizable-column-id="name"
                                                    >
                                                        Name
                                                    </th>
                                                    <th data-field="date_add_time"
                                                        data-width={this.state.columnsWidth.date_added}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.date_added}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="date_added"
                                                    >
                                                        Date Added
                                                    </th>
                                                    <th data-field="email"
                                                        data-width={this.state.columnsWidth.email}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.email}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="email"
                                                    >
                                                        Email
                                                    </th>
                                                    <th data-field="respond_status"
                                                        data-width={this.state.columnsWidth.respond_status}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.respond_status}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="respond_status"
                                                    >
                                                        Integration
                                                    </th>
                                                    <th data-field="fb_id"
                                                        data-width={this.state.columnsWidth.profile_id}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.profile_id}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="profile_id"
                                                    >
                                                        Profile Id
                                                    </th>
                                                    <th data-field="a1"
                                                        data-width={this.state.columnsWidth.Q1_answer}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.Q1_answer}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="Q1_answer"
                                                    >
                                                        Q1 Answer
                                                    </th>
                                                    <th data-field="a2"
                                                        data-width={this.state.columnsWidth.Q2_answer}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.Q2_answer}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="Q2_answer"
                                                    >
                                                        Q2 Answer
                                                    </th>
                                                    <th data-field="a3"
                                                        data-width={this.state.columnsWidth.Q3_answer}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.Q3_answer}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="Q3_answer"
                                                    >
                                                        Q3 Answer
                                                    </th>
                                                    <th data-field="phone_number"
                                                        data-width={this.state.columnsWidth.phone_number}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.phone_number}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="phone_number"
                                                    >
                                                        Phone Number
                                                    </th>
                                                    <th data-field="notes"
                                                        data-width={this.state.columnsWidth.notes}
                                                        data-width-unit="%"
                                                        data-visible={!this.state.columnsVisibility.notes}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="notes"
                                                    >
                                                        Notes
                                                    </th>
                                                    <th data-field="tags"
                                                        data-width={this.state.columnsWidth.tags}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.tags}
                                                        data-align="center"
                                                        data-resizable-column-id="tags"
                                                        data-formatter="renderTagsCell"
                                                    >
                                                        Tags
                                                    </th>
                                                    <th data-field="approved_by"
                                                        data-width={this.state.columnsWidth.approved_by}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.approved_by}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="approved_by"
                                                        data-formatter="renderApprovedByCell"
                                                    >
                                                        Approved By
                                                    </th>
                                                    <th data-field="invited_by"
                                                        data-width={this.state.columnsWidth.invited_by}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.invited_by}
                                                        data-align="center"
                                                        data-resizable-column-id="invited_by"
                                                    >
                                                        Invited By
                                                    </th>
                                                    <th data-field="lives_in"
                                                        data-width={this.state.columnsWidth.lives_in}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.lives_in}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="lives_in"
                                                    >
                                                        Lives In
                                                    </th>
                                                    <th data-field="agreed_group_rules"
                                                        data-width={this.state.columnsWidth.agreed_group_rules}
                                                        data-width-unit="%"
                                                        data-visible={this.state.columnsVisibility.agreed_group_rules}
                                                        data-sortable="true"
                                                        data-align="center"
                                                        data-resizable-column-id="agreed_group_rules"
                                                        data-formatter="renderYesOrNoCell"
                                                    >
                                                        Agreed to Rules
                                                    </th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    id="waitingoverlay"
                    style={this.state.showDetailsScreenLoader === 1 ? {display: 'block'} : {display: 'none'}}
                >
                    <div className="loader-demo-box sectionLoader">
                        <div className="bar-loader">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span className="integration-message-text">
                        Please wait while we are sending your data to auto-responder...
                    </span>
                    </div>
                </div>
            </div>
        );
    }
}
export default Details;
