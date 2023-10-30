import React, { Component } from 'react';
import { BootstrapTable, PaginationList, TableHeaderColumn, ToolBar } from 'react-bootstrap-table';
import Select from 'react-select';
import moment from 'moment';
import {API} from './Api'
import * as Constants from './Constants'
import Modal from 'react-bootstrap/Modal'
import swal from 'sweetalert'

import ActiveCampaign from './Autoresponder/ActiveCampaign'
import Mailerlite from './Autoresponder/Mailerlite'
import GetResponse from './Autoresponder/GetResponse'
import ConvertKit from './Autoresponder/ConvertKit'
import MailChimp from './Autoresponder/MailChimp'
import GoHighLevel from './Autoresponder/GoHighLevel'
import Kartra from './Autoresponder/Kartra'
import Aweber from './Autoresponder/Aweber'
import GoogleSheet from './Autoresponder/GoogleSheet'
import OntraPort from './Autoresponder/OntraPort';
import InfusionSoft from './Autoresponder/InfusionSoft';

/** Csv Component */
import CsvImport from './CsvImport'
import Import from './Import'
import DragScrollBehavior from './ComponentBehaviors/DragScrollBehavior'
import whenJobComplete from './AsyncJobs';

class Home extends Component{
    constructor(props) {
        super(props);
        this.state={
            addMember:0,
            selectGroupId:0,
            integrationsScreen:0,
            autoResponder:'',
            membersUrl:"",
            membersError:"",
            selectGroup:{},
            removeAutoresponder:0,
            autoSelectDisabled:false,
            autoResponderValues:[
                { value: '', label: 'Select your integration...' },
                { value: 'Aweber', label: 'Aweber' },
                { value: 'Getresponse', label: 'Getresponse' },
                { value: 'ConvertKit', label: 'ConvertKit' },
                { value: 'ActiveCampaign', label: 'ActiveCampaign' },
                { value: 'MailChimp', label: 'MailChimp' },
                { value: 'GoHighLevel', label: 'GoHighLevel' },
                { value: 'Kartra', label: 'Kartra' },
                { value: 'GoogleSheet', label: 'Google Sheet' },
                { value: 'Mailerlite', label: 'Mailerlite' },
                { value: 'OntraPort', label: 'OntraPort'},
                { value: 'InfusionSoft', label: 'Keap (Infusionsoft)'},
            ],
            collapseClass:'',
            csv_headers:[
                { label: "ID", key: "id" },
                { label: "DATE ADDED", key: "date_added" },
                { label: "AUTORESPONDER", key: "autoresponder" },
                { label: "FULL NAME", key: "full_name" },
                { label: "FIRST NAME", key: "first_name" },
                { label: "LAST NAME", key: "last_name" },
                { label: "EMAIL ADDRESS", key: "email" },
                { label: "USER ID", key: "user_id" },
                { label: "Q1 ANSWER", key: "q1" },
                { label: "Q2 ANSWER", key: "q2" },
                { label: "Q3 ANSWER", key: "q3" },
                { label: "NOTES", key: "notes" },
                { label: "TAGS", key: "tags" },
                { label: "APPROVED BY", key: "approved_by"},
                { label: "INVITED BY", key: "invited_by" },
                { label: "LIVES IN", key: "lives_in" },
                { label: "AGREED TO GROUP RULES", key: "agreed_group_rules" },
            ],
            csv_data:[],
            csv_filename:'',
            scrapingLoader:0,
            importCsv:0,
            addMemberStep:1,
            scrapeMemberCount: 0,
            selectGroups:{ label:'All Groups', value:'all'},
            showInstallExtensionModal: 0,
            addExitsMemberErrorMessage: '',
        }
        this.handleAddExistingMembersData=this.handleAddExistingMembersData.bind(this)
    }
    componentDidMount(){
        this.DefulatsetCollapse()
        DragScrollBehavior.dragScroll('.react-bs-container-body');
        window.addEventListener('message', this.handleAddExistingMembersData, false)
    }
    componentWillUnmount() {
        window.removeEventListener('message', this.handleAddExistingMembersData, false)
    }
    handleAddExistingMembersData(event) {
        if (event.data.type === "SendMemberScrape") {
            let scrapedData = event.data.data;

            if (scrapedData.stop) {
                this.setState({ addMemberStep: 3 });
                return this.props.getGroups();
            }

            const requestData = {
                group: scrapedData[0].group,
                members: scrapedData[0].user_details,
            };

            API.addMembers(requestData)
                .then((res) => {
                    if (res.status !== 200) {
                        let message = res.response.data.message;
                        /**
                         * Here we change the default message that comes from the backend,
                         * how the notification to the member would have sense and be understandable
                         * by the user.
                        */
                        if (message == 'The members field is required.') {
                            message = 'You do not have access to add members from this group.';
                        }
                        this.setState({ addExitsMemberErrorMessage: message });
                        return this.stopScraping();
                    }

                    this.setState({
                        scrapeMemberCount: this.state.scrapeMemberCount + requestData.members.length
                    });
                })
                .catch((error) => {
                    this.setState({ addExitsMemberErrorMessage: error });
                    this.stopScraping();
                });
        }
    }
    componentWillReceiveProps(props) {
        //this.setState({groups:props.groups})
    }
    handleChange(event){
        this.setState({membersUrl:event.target.value});
    };
    async saveMember(){
        this.setState({ scrapingLoader: 1});
        const facebookMembersURLRegex = /(?:https?:\/\/)([a-zA-Z.]*)(.facebook.com)\/(groups)\/([a-zA-Z0-9.]*)/;
        let facebookMembersURL = this.state.membersUrl.trim();
        if (!facebookMembersURL.match(facebookMembersURLRegex)) {
            swal({
                text:`You have entered an invalid URL. Please try again!`,
                buttons: true,
            });
            return this.setState({scrapingLoader: 0});
        }
        facebookMembersURL = facebookMembersURL.match(facebookMembersURLRegex)[4];
        this.setState({addMemberStep: 2});
        await this.getMembersScrapingDetails(facebookMembersURL);
    }
    getMembersScrapingDetails(fb_basic_url){
        var self=this
        return new Promise(function(resolve) {
            var data = { type: "sendMembersScrapingDetails",'url':fb_basic_url};
            window.postMessage(data, "*");
        });
    }
    addMemberForm(){
        if(this.state.addMemberStep==1){
            return(
                <Modal
                dialogClassName="modal-90w"
                show={this.state.addMember ? true : false}
                onHide={this.setaddMemberForm.bind(this,0)}
                backdrop="static"
                keyboard={false}
                >
                    <Modal.Header closeButton>
                        <Modal.Title>Enter Your <b>Group's Members Page URL</b> Below</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <small><b>Note:</b> Due to limitations with Facebook™ we can only add approx. 9,500 members.</small>
                        <div className="alert alert-fill-danger" role="alert" style={this.state.membersError ? {display:'block'} : {display:'none'}}>
                            <i className="fa fa-exclamation-triangle"></i>
                            {this.state.membersError}
                        </div>
                        <div className="form-group">
                            <img src="/asset/images/your_group_name.png" className="img-responsive" id="your_group_name_el" />
                        </div>
                        <div className="form-group">
                            <input type="text" className="form-control" value={this.state.membersUrl} onChange={this.handleChange.bind(this) } placeholder="https://www.facebook.com/groups/name/members/" />
                        </div>
                    </Modal.Body>
                    <Modal.Footer className="footer-center px-0">
                        <div>
                            <button type="button" className="btn btn-primary" onClick={this.saveMember.bind(this)} disabled={this.state.scrapingLoader ? 'disabled' : '' }>
                                ADD EXISTING MEMBERS NOW
                                <i className="fa fa-spinner fa-spin fa-3x fa-fw ml-2 loader_spinner" style={ this.state.scrapingLoader ? {display:'block'} : {display:'none'}} ></i>
                            </button>
                            <button type="button" className="btn btn-light" id="btnCloseMember" onClick={this.setaddMemberForm.bind(this,0)}>Close</button>
                        </div>
                    </Modal.Footer>
                </Modal>
            )
        }
        if(this.state.addMemberStep==2){
            return(
                <Modal
                dialogClassName="modal-90w"
                show={this.state.addMember ? true : false}
                backdrop="static"
                keyboard={false}
                >
                    <Modal.Body>
                        <div className="groupkit_logo">
                            <img  src="asset/images/logo.png" width="330" /></div>
                        <br/>
                        <div className="groupkit_text">Hang tight!
                            <img width="32" src="/asset/images/stopwatchsmall.png"/> We are now adding<br/>your existing group members.
                        </div>
                        <div id="egxisting_members_scrape_title_after">
                            <b id="num_mem_scrapped"> { this.state.scrapeMemberCount  } </b> members...
                        </div>
                        <div className="text-center"><button onClick={this.stopScraping.bind(this)} id="all_button_stop_gk" type="button" className="stopButton">Stop</button></div>
                    </Modal.Body>
                </Modal>
            )
        }

        if(this.state.addMemberStep==3){
            return(
                <Modal
                dialogClassName="modal-90w"
                show={this.state.addMember ? true : false}
                backdrop="static"
                keyboard={false}
                >
                    <Modal.Body>
                        <div className="groupkit_logo">
                            <img  src="/asset/images/logo.png" width="330" /></div>
                        <br/>
                        {this.state.addExitsMemberErrorMessage ?
                            <div className="text-center"><h4 dangerouslySetInnerHTML={{ __html: this.state.addExitsMemberErrorMessage }}></h4></div>
                        :
                            <div className="groupkit_text">Success
                                <img width="32" src="/asset/images/party_pop.png"/> Your existing group members have been added.
                            </div>
                        }
                        <div className="text-center">
                            <button
                                id={
                                    this.state.addExitsMemberErrorMessage ?
                                        'all_button_stop_gk' :
                                        'all_button_stop_done'
                                }
                                type="button"
                                className="stopButton"
                                onClick={this.setaddMemberForm.bind(this,0)}
                            >
                            { this.state.addExitsMemberErrorMessage ? 'Close' : 'Done' }
                            </button>
                        </div>
                    </Modal.Body>
                </Modal>
            )
        }
    }
    stopScraping(){
        var data = { type: "sendMembersScrapingStop",'url':''};
        window.postMessage(data, "*");

        this.setState({addMemberStep:3,addMember:3})
    }
    async setaddMemberForm(parm){
        if(parm==1){
            var response=await this.isExtensionInstalled()
            if(response===false){
                return
            }
        }
        this.setState({scrapingLoader:0})
        this.setState({addMember:parm})
        this.setState({addMemberStep:1})
        this.setState({scrapeMemberCount: 0})
        this.setState({membersUrl:''})
        this.setState({addExitsMemberErrorMessage: ''})
    }

    /**
     * Downloads provided group members CSV if customer isn't already in process of downloading the same CSV
     *
     * @param {object} group which group members will be downloaded in csv file
     */
    buildMembersCSV(group) {
        if (group.csv_file_download_disabled) {
            return;
        }
        swal('We are building a CSV file for you. You will need to wait depending on the number of group members.');

        this.props.onToggleGroupCSVDownloadStatus(group.id);

        API.buildGroupMembersCsvFile({ group_id: group.id, is_multi_page_select_all: true})
            .then(({ data: { async = false, job_id = null, message = '', file_name }, status}) => {
                if (async) {
                    whenJobComplete(job_id).then(() => {
                        this.props.onBuildMembersCSV(file_name);
                        this.props.onToggleGroupCSVDownloadStatus(group.id);
                    });
                } else {
                    this.props.onBuildMembersCSV(file_name);
                    this.props.onToggleGroupCSVDownloadStatus(group.id);
                    swal('The file with the group members has been successfully downloaded.');
                }
            })
            .catch(e => {
                this.props.onToggleGroupCSVDownloadStatus(group.id);
                swal('There is a problem with downloading the members. Please try again.', {icon: 'error'});
            });
    }

    // delete
    deleteGroup(id){
        swal({
            text: "Are you sure you want to do this?",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                API.deleteGroup(id).then((res) => {
                    if(res.data.code==200){
                        swal(res.data.message,{
                            icon: "success",
                        });
                        this.props.removeGroup(id);
                    }
                }).catch((error) => {
                    //console.log(error)
                });
            }
        });

    }
    regionsFormatter(cell, row){
        return(
            <div className="row action icons">
                <div
                    className={'col-sm-12 col-md-6 col-lg-4 p-1 ' + (row.csv_file_download_disabled ? 'cursor-not-allowed' : 'cursor-pointer')}
                    aria-disabled={row.csv_file_download_disabled}
                    onClick={this.buildMembersCSV.bind(this, row)}
                >
                    <h6>
                        <i className={'fa fa-file-excel-o ' + (row.csv_file_download_disabled ? 'cursor-not-allowed' : '')} />
                    </h6>
                    <p>Download CSV</p>
                </div>
                <a className="col-sm-12 col-md-6 col-lg-4 cursor-pointer text-decoration-none heading-color p-1" href={ '/groups/' + row.id }>
                    <h6><i className="fa fa-external-link"></i></h6>
                    <p>View Members</p>
                </a>
                { this.props.user.access_team === true && this.props.user.id === row.user_id &&
                <div className="col-sm-12 col-md-6 col-lg-4 cursor-pointer p-1" onClick={this.deleteGroup.bind(this, row.id)} >
                    <h6><i className="fa fa-trash-o removeicon"></i></h6>
                    <p>Remove Group</p>
                </div>
                }
            </div>
        )
    }
    membersCount(cell, row){
        return row?.members_count?.members ?? 0;
    }
    memberName(cell, row){
        return (
        <a className="link text-link cursor-pointer" href={ '/groups/' + row.id }>{row.fb_name}</a>
        )
    }
    respondType(cell, row){
        if (row.responder.length) {
            const responder = this.state.autoResponderValues.find(responder => {
                return responder.value === row.responder[0].responder_type;
            });

            return (
                <span
                    onClick={this.props.user.access_team ? this.activeConfigured.bind(this, 1, row) : null}
                    className="activeConfigured cursor-pointer"
                >
                    <i className="fa fa-cogs"/> { responder.label } (Configured)
                </span>
            );
        }

        return (
            <span
                onClick={this.props.user.access_team ? this.activeConfigured.bind(this, 1, row) : null}
                className="activeConfigured cursor-pointer"
            >
                <i className="fa fa-cogs"/> Setup Now (Not Configured)
            </span>
        );
    }
    activeConfigured(parm,data){
        if(data){
            this.setState({selectGroupId:data.id})
            this.setState({selectGroup:data})
            if(data.responder.length){
                var responder=data.responder[0]
                this.setState({
                    autoResponder:{value: responder.responder_type, label: responder.responder_type},
                    autoSelectDisabled:true
                })
            }else{
                this.setState({
                    autoResponder:{ value: '', label: 'Select your integration...' },
                    autoSelectDisabled:false,
                    removeAutoresponder:0
                })
            }
        }
        this.setState({integrationsScreen:parm})
    }
    showRemoveAutoresponder(){
        this.setState({removeAutoresponder:1})
    }
    setActiveAutoresponder(type){
        var data=this.state.selectGroup;
        if(data.responder.length){
            var responder=data.responder[0]
            if(type==responder.responder_type){
                var Object=JSON.parse(responder.responder_json)
                Object.responder_type=responder.responder_type;
                Object.group_id=data.id;
                Object.user_id=this.props.user.id;
                this.setState({removeAutoresponder:1})
            }else{
                var Object={}
                Object.group_id=data.id;
                Object.user_id=this.props.user.id;
                this.setState({removeAutoresponder:0})
            }
        }else{
            var Object={}
            Object.group_id=data.id;
            Object.user_id=this.props.user.id;
            this.setState({removeAutoresponder:0})
        }
        return Object
    }
    selectAutoResponder(event){
        this.setState({autoResponder:event});
    }
    removeAutoresponder(){
        swal({
            text: "Are you sure you want to do this?",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                this.removeAutoresponderApi()
            }
        });
    }
    removeAutoresponderApi(){
        var Object={
            group_id:this.state.selectGroupId
        }
        API.deleteAutoresponder(Object).then((res) => {
            if(res.data.code==200){
                swal("Your integration has been removed.",{
                    icon: "success",
                });
                this.props.removeResponderFromGroup(this.state.selectGroupId);
                this.activeConfigured(0)
            }
        }).catch((error) => {
            swal('Invalid Request',{
                icon: "error",
            });
        });
    }
    integrationsForm(){
        return(
            <Modal
            dialogClassName="modal-90w"
            show={this.state.integrationsScreen ? true : false}
            onHide={this.activeConfigured.bind(this,0,'')}
            backdrop="static"
            keyboard={false}
            >
                <Modal.Header closeButton>
                    <Modal.Title>Select An Integration For Your Group</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <div className="modal-body">
                        <div className="form-group row col-12 p-0 pt-2 m-0">
                            <label className="col-12 p-0">Integration</label>
                            <Select
                                className="col-12 p-0"
                                isDisabled={this.state.autoSelectDisabled}
                                value={this.state.autoResponder}
                                onChange={this.selectAutoResponder.bind(this)}
                                options={this.state.autoResponderValues}
                            />
                        </div>
                        <br/>
                        {  this.state.autoResponder.value=='ActiveCampaign' ?
                                (<ActiveCampaign
                                    setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                    showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                    getGroups={this.props.getGroups.bind(this)}
                                />)
                            : ''
                        }
                        {  this.state.autoResponder.value=='Mailerlite' ?
                                (<Mailerlite
                                    setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                    showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                    getGroups={this.props.getGroups.bind(this)}
                                />)
                            : ''
                        }
                        {  this.state.autoResponder.value=='Getresponse' ?
                                (<GetResponse
                                    setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                    showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                    getGroups={this.props.getGroups.bind(this)}
                                />)
                            : ''
                        }
                        {  this.state.autoResponder.value=='ConvertKit' ?
                                (<ConvertKit
                                    setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                    showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                    getGroups={this.props.getGroups.bind(this)}
                                />)
                            : ''
                        }
                        {  this.state.autoResponder.value=='MailChimp' ?
                                (<MailChimp
                                    setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                    showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                    getGroups={this.props.getGroups.bind(this)}
                                />)
                            : ''
                        }
                        {  this.state.autoResponder.value=='GoHighLevel' ?
                                (<GoHighLevel
                                    setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                    showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                    getGroups={this.props.getGroups.bind(this)}
                                />)
                            : ''
                        }
                        {  this.state.autoResponder.value=='Kartra' ?
                                (<Kartra
                                    setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                    showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                    getGroups={this.props.getGroups.bind(this)}
                                />)
                            : ''
                        }
                        {  this.state.autoResponder.value=='Aweber' ?
                                (<Aweber
                                    setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                    showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                    removeAutoresponderApi={this.removeAutoresponderApi.bind(this)}
                                    getGroups={this.props.getGroups.bind(this)}
                                />)
                            : ''
                        }
                        {  this.state.autoResponder.value=='GoogleSheet' ?
                                (<GoogleSheet
                                    setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                    showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                    getGroups={this.props.getGroups.bind(this)}
                                />)
                            : ''
                        }
                        {  this.state.autoResponder.value === 'OntraPort' &&
                            <OntraPort
                                setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                getGroups={this.props.getGroups.bind(this)}
                            />
                        }
                        {  this.state.autoResponder.value === 'InfusionSoft' &&
                            <InfusionSoft
                                setActiveAutoresponder={this.setActiveAutoresponder.bind(this)}
                                showRemoveAutoresponder={this.showRemoveAutoresponder.bind(this)}
                                getGroups={this.props.getGroups.bind(this)}
                            />
                        }
                        <small className="intigration-autoresponder" style={this.state.autoResponder.value && this.state.removeAutoresponder == 0 ? {display:'block'} : {display:'none'}}>Need help configuring this integration?
                            <a className="" data-toggle="tooltip" data-placement="top" data-trigger="hover" data-animation="false" title="" href={API.integrationAutoresponderLink(this.state.autoResponder.value)}  target="_blank" data-original-title="Click here">  Click here.
                            </a>
                        </small>
                        <div className="form-group row" style={this.state.removeAutoresponder ? {display:'block'} : {display:'none'}}>
                            <div className="col-sm-12 text-center removeAutoresponder">
                                <a href="#" className="text-ling" onClick={this.removeAutoresponder.bind(this)}>Remove Integration</a>
                            </div>
                        </div>
                    </div>
                    </Modal.Body>
            </Modal>
        )
    }
    DefulatsetCollapse(){
        if(localStorage.getItem('collapse')=="true" && localStorage.getItem('collapse') !=undefined){
            this.setState({collapseClass:'collapse show'})
        }else{
            this.setState({collapseClass:'collapse'})
        }
    }
    setCollapse(){
        if(localStorage.getItem('collapse')=="true" && localStorage.getItem('collapse') !=undefined){
            localStorage.setItem('collapse',false)
            this.setState({collapseClass:'collapse'})
        }else{
            localStorage.setItem('collapse',true)
            this.setState({collapseClass:'collapse show'})
        }
    }
    selectGroupSearch(e){
        if(e.value!='all' && e.value){
            this.props.groupsFilter(e.value)
            this.refs.groupNameCol.applyFilter(e.value.toString());
        }else{
            this.refs.groupNameCol.applyFilter('');
            this.props.getGroups()
        }
        this.setState({selectGroups:e})
    }
    setNumberofList(){
        var showNumber=10
        if(localStorage.getItem('groupSizePerPage')!=null){
            showNumber=localStorage.getItem('groupSizePerPage')
        }
        return({
            sizePerPage:parseInt(showNumber),
            sizePerPageList:[10,25,50,100],
            onSizePerPageList: this.sizePerPageListChange.bind(this),
            noDataText: 'You currently have no groups. To add your group, simply approve a new member using GroupKit.',
        });
    }
    sizePerPageListChange(sizePerPage) {
        localStorage.setItem('groupSizePerPage',sizePerPage)
    }
    isExtensionInstalled(){
        return new Promise(resolve => {
            var response=false
            if(document.querySelector('#groupkit_cloud_store')!=null
                && document.querySelector('#groupkit_cloud_store')){
                    response=true
            }else{
                this.setState({showInstallExtensionModal: 1})
                response=false
            }
            resolve(response)
        })
    }
    closeExtensionModal(){
        this.setState({showInstallExtensionModal: 0})
    }
    extensionModal(){
        return(
            <Modal
                size="lg"
                dialogClassName="extensionModal"
                show = {this.state.showInstallExtensionModal ? true : false}
                onHide={this.closeExtensionModal.bind(this)}
                backdrop="static"
                keyboard={false}
                >
                    <Modal.Header className="border-0 p-0 m-0" closeButton>
                    </Modal.Header>
                    <Modal.Body>
                        <div className="row text-center">
                            <div className="col-sm-12 col-md-12">
                                <img className="chrome-web-store" src="/asset/images/chromewebstore-en.png" />
                            </div>
                            <div className="col-sm-12 col-md-12 label_text">In order to use this feature, you need to install the extension from Chrome webstore.</div>
                            <div className="col-sm-12 col-md-12 p-3">
                                <a className="btn btn-sm btn-primary extension-btn" href={Constants.EXTENSION_URL} target="blank">INSTALL EXTENSION</a>
                            </div>
                        </div>
                    </Modal.Body>
            </Modal>
        )
    }

    render() {
        return (
            <div className="container-fluid page-body-wrapper m-0 p-0">

                {this.state.addMember ? this.addMemberForm() : ''}
                {this.state.integrationsScreen ? this.integrationsForm() : ''}
                {this.state.showInstallExtensionModal ? this.extensionModal() : ''}

                <div className="main-panel">
                    <div className="content-wrapper my-5">
                        <div className="row">
                            <div className="col-md-6 col-lg-3 grid-margin stretch-card">
                                <div className="card bg-gradient-primary text-white text-center card-shadow-primary">
                                    <div className="card-body">
                                    <h6 className="font-weight-normal">New Members This Week</h6>
                                    <h2 className="mb-0">{this.props.weeks_members_count}</h2>
                                    </div>
                                </div>
                            </div>
                            <div className="col-md-6 col-lg-3 grid-margin stretch-card">
                                <div className="card bg-gradient-danger text-white text-center card-shadow-danger">
                                    <div className="card-body">
                                    <h6 className="font-weight-normal">New Members Today</h6>
                                    <h2 className="mb-0">{this.props.todays_members_count}</h2>
                                    </div>
                                </div>
                            </div>
                            <div className="col-md-6 col-lg-3 grid-margin stretch-card">
                                <div className="card bg-gradient-warning text-white text-center card-shadow-warning">
                                    <div className="card-body">
                                    <h6 className="font-weight-normal">New Emails This Week</h6>
                                    <h2 className="mb-0">{this.props.weeks_emails_added_count}</h2>
                                    </div>
                                </div>
                            </div>
                            <div className="col-md-6 col-lg-3 grid-margin stretch-card">
                                <div className="card bg-gradient-info text-white text-center card-shadow-info">
                                    <div className="card-body">
                                    <h6 className="font-weight-normal">New Emails Today</h6>
                                    <h2 className="mb-0">{this.props.todays_emails_added_count}</h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="row">
                            <div className="col-12 accordion">
                                <div className="card">
                                    <div className="card-header" role="tab" id="heading-1">
                                        <h4 className="mb-0">
                                            <a className="cursor-pointer watchTitle" onClick={this.setCollapse.bind(this)}
                                                aria-expanded={localStorage.getItem('collapse')=="true" && localStorage.getItem('collapse') !=undefined ? "true" : "false"}
                                            >
                                                New To GroupKit? Watch Our QuickStart Video
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="collapse-1" className={this.state.collapseClass}>
                                        <div className="card-body">
                                            <div className="row">
                                                <div className="col-sm-12 col-md-6 col-lg-6">
                                                    <iframe
                                                        src="https://landonstewart10.wistia.com/embed/iframe/iom3qmp46a?seo=false&amp;videoFoam=true"
                                                        className="load_video"
                                                        allowtransparency="true"
                                                        frameBorder="0"
                                                        scrolling="no"
                                                        allowFullScreen
                                                    />
                                                </div>
                                                <div className="col-sm-12 col-md-6 col-lg-6">
                                                    <div>
                                                        <p className="font-18 text-justify">
                                                            When you approve a pending member request in your group,
                                                            GroupKit will automagically begin storing your member
                                                            data for you. Pretty cool, huh?
                                                        </p>
                                                        <p className="font-18 text-justify">
                                                            From this page, you can see all of the groups
                                                            you are managing, view your group member data,
                                                            and even configure an autoresponder so that your
                                                            group member's email addresses are instantly added
                                                            to a list of your choice.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="row">
                            <div className="col-sm-12 col-md-12 col-lg-3 group_title">
                                <h4 className="card-title">Manage Your Groups</h4>
                            </div>

                            <div className="col-sm-12 col-md-6 col-lg-2">
                                <Select
                                    isSearchable={false}
                                    value={this.state.selectGroups}
                                    className="select_group_picker"
                                    placeholder="Select Groups"
                                    onChange={this.selectGroupSearch.bind(this)}
                                    options= {
                                        [{ label:'All Groups', value:'all'}].concat(this.props.groups.map((e, key) => {
                                            return { label:e.fb_name, value:e.id}
                                        }).filter(function( data ) {
                                            return data !== undefined;
                                        }))}
                                />
                            </div>

                            {this.props.user.access_team==true ?
                                <div className="import_btn col-sm-12 col-md-6 col-lg-2">
                                    <Import
                                        getGroups={this.props.getGroups.bind(this)}
                                    />
                                </div>
                            :''}

                            {this.props.user.access_team==true ?
                                <div className="import_btn col-sm-12 col-md-6 col-lg-2">
                                    <CsvImport
                                        getGroups={this.props.getGroups.bind(this)}
                                        isExtensionInstalled={this.isExtensionInstalled.bind(this)}
                                    />
                                </div>
                            :''}

                            {this.props.user.access_team==true ?
                                <div className="import_btn existing_btn col-sm-12 col-md-6 col-lg-3">
                                    <button className="btn btn-sm btn-primary p-1" onClick={this.setaddMemberForm.bind(this,1)}>
                                        <i className="fa fa-plus-square-o"></i> ADD EXISTING MEMBERS
                                    </button>
                                </div>
                            : ''}
                        </div>
                        <div className="row">
                            <div className="col-12">
                                <div className="card">
                                    <div className="card-body">
                                        <div className="groups_table">
                                            <BootstrapTable options={this.setNumberofList()} data={this.props.groups} ref='table'  pagination={ true } search={ false }>
                                                <TableHeaderColumn
                                                    width='300'
                                                    columnClassName='td-body_group'
                                                    className='td-header_group'
                                                    ref='groupNameCol'
                                                    filter={{type:'TextFilter',condition: 'eq' ,delay: 1000 } }
                                                    isKey
                                                    dataSort={ true }
                                                    dataAlign="center"
                                                    dataField="id"
                                                    dataFormat={ this.memberName.bind(this) }
                                                >
                                                    Group Name
                                                </TableHeaderColumn>
                                                <TableHeaderColumn
                                                    width='150'
                                                    dataSort={ true }
                                                    dataAlign="center"
                                                    dataField="Members"
                                                    dataFormat={ this.membersCount.bind(this) }
                                                >
                                                    Members
                                                </TableHeaderColumn>
                                                <TableHeaderColumn
                                                    width='250'
                                                    dataSort={ true }
                                                    dataAlign="center"
                                                    dataField="Integrations"
                                                    dataFormat={ this.respondType.bind(this) }
                                                >
                                                    Integrations
                                                </TableHeaderColumn>
                                                <TableHeaderColumn
                                                    width='250'
                                                    export={ false }
                                                    dataFormat={ this.regionsFormatter.bind(this) }
                                                    dataAlign="center"
                                                >
                                                    Options
                                                </TableHeaderColumn>
                                            </BootstrapTable>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }
}
export default Home;
