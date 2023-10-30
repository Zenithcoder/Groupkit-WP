import React, { Component } from 'react';
import moment from 'moment-timezone';
import Home from './Home';
import Details from './Details';
import {API} from './Api';
import axios from "axios";

import betaTesters from '../data/beta-testers.json';

class Main extends Component{
    constructor(props) {
        super(props);
        this.state = {
            screen:0,
            dropDown:0,
            groups:[],
            groupsDetails:[],
            user:[],
            todaysEmailsAddedCount: 0,
            todaysMembersCount: 0,
            weeksEmailsAddedCount: 0,
            weeksMembersCount: 0,
            screenLoader:1,
            membersFound: 0
        };
        this.getUser()
        this.handleClickOutside = this.handleClickOutside.bind(this)
    }

    /**
     * After this component is rendered in DOM:
     * close menu on click outside of menu is activated
     * the Details component is displayed if the user is on the group page.
     *
     */
    componentDidMount() {
        document.addEventListener('mousedown', this.handleClickOutside);
        if (this.isGroupsPage() && this.getRouteId()) {
            this.groupDetails(this.getRouteId());
        }

        window.addEventListener('message', async function (event) {
            if (event.data.type === 'refresh_data') {
                if (document.querySelector('#btnCloseMember')) {
                    document.querySelector('#btnCloseMember').click();
                }

                if (!document.querySelector('.details_screen')) {
                    this.getGroups();
                }
            }
        }.bind(this));
    }

    componentWillUnmount() {
        document.removeEventListener('mousedown', this.handleClickOutside);
    }

    handleClickOutside(e) {
        if (
            !e.target.className instanceof SVGAnimatedString
            && e.target.className.indexOf('show-dropdwon') === -1
            && e.target.className.indexOf('dropdown-item') === -1
            && this.state.dropDown === 1
        ) {
            this.setState({ dropDown: 0 });
        }
    }

    /**
     * Get current route name
     *
     * @returns {string} route name
     */
    getRoute() {
        let route = window.location.pathname;
        route = route.substring(1);
        route = route.split('/');

        return route[0];
    }

    /**
     * Get current route id if exists otherwise null
     *
     * @returns {int|null} the route id
     */
    getRouteId() {
        let route = window.location.pathname;
        route = route.substring(1);
        route = route.split('/');
        if (route[1] !== undefined && parseInt(route[1])) {
            return parseInt(route[1]);
        }
        return null;
    }

    /* get user */
    getUser(){
        if(this.state.user==''){
            API.getUser().then((res) => {
                if (res.data.code === 200) {
                    const current_user = res.data.data.user;

                    this.setState({user: current_user});
                    this.getGroups();

                    // If no timezone is set in the database, we autodetect it and set one
                    if (!current_user.timezone) {
                        axios(
                            '/user/update',
                            {
                                method: 'POST',
                                data: {
                                    'updateOnlyTimeZone': true,
                                    'timeZone': moment.tz.guess(true),
                                },
                            }
                        );
                    }
                }
            }).catch((error) => {
                // If an authentication token doesn't exist, the user should be logged out
                if (!localStorage.getItem('current_session')) {
                    this.logOut();
                }
            });
        }else{
            this.getGroups();
        }
    }
    /* get all groups details */
    getGroups(){
        API.groups().then((res) => {
            if(res.data.code==200){
                const groups = res.data.data.groups.map((group) => {
                    group.csv_file_download_disabled = false;

                    return group;
                });

                this.setState({
                    groups: groups,
                    todaysEmailsAddedCount: res.data.data.todays_emails_added_count,
                    todaysMembersCount: res.data.data.todays_members_count,
                    weeksEmailsAddedCount: res.data.data.weeks_emails_added_count,
                    weeksMembersCount: res.data.data.weeks_members_count,
                    screenLoader: 0,
                })
            }
        }).catch((error) => {
           //console.log(error)
        });
    }

    /**
     * Toggles download allowance for group with provided id
     *
     * @param {int} group_id of the group which download csv access will be enabled or disabled
     */
    toggleGroupCSVDownloadStatus(group_id) {
        const groups = this.state.groups.map(group => {
            if (group.id === group_id) {
                group.csv_file_download_disabled = !group.csv_file_download_disabled;
            }
            return group;
        });

        this.setState({ groups: groups });
    }

    /* get all groups details groupsByID */
    groupsFilter(id){
        API.groupsByID(id).then((res) => {
            if(res.data.code==200){
                this.setState({groups:res.data.data.groups})
                this.setState({
                    todaysEmailsAddedCount: res.data.data.todays_emails_added_count,
                    todaysMembersCount: res.data.data.todays_members_count,
                    weeksEmailsAddedCount: res.data.data.weeks_emails_added_count,
                    weeksMembersCount: res.data.data.weeks_members_count
                })
            }
        }).catch((error) => {
           //console.log(error)
        });
    }
    /* get groups details */
    getGroupsDetails(id){
        API.groupsDetails(id).then((res) => {
            if(res.data.code==200){
                this.setState({groupsDetails:res.data.data.group})
            }
        }).catch((error) => {
           //console.log(error)
        });
    }
    /* filter Member details */
    filterMember(parm){
        return API.filterMember(parm).then((res) => {
            if(res.data.code==200){
                this.setState(
                    {
                        groupsDetails: res.data.data.group,
                        membersFound:  res.data.data.members_found
                    }
                );
            }
        }).catch((error) => {
           //console.log(error)
        });
    }

    /**
     * Downloads group members csv file in new tab
     *
     * @param {String} fileName with extension which we will download in the current tab
     */
    downloadMembersCSV(fileName) {
        window.open(`/group-members/csv/${fileName}`, '_self');
    }

    /**
     * Removes autoresponder from the group with provided groupId
     *
     * @param {int} groupId
     */
    removeResponderFromGroup(groupId) {
        let groups = this.state.groups;
        const groupIndex = groups.findIndex(group => group.id === groupId);
        groups[groupIndex].responder = [];

        this.setState({ groups });
    }

    /**
     * Removes deleted API group from the groups locally
     *
     * @param {int} id of the deleted group
     */
    removeGroup(id) {
        this.setState({
            groups: this.state.groups.filter(group => group.id !== id)
        });
    }

    backToMain(){
        this.getGroups()
        this.setScreen(0)
    }
    groupDetails(id){
        this.getGroupsDetails(id)
        this.setScreen(1)
    }
    setScreen(parm){
        this.setState({screen:parm})
    }
    setDropDown(parm){
        if(this.state.dropDown){
            this.setState({dropDown:0})
        }else{
            this.setState({dropDown:1})
        }
    }
    logOut(){
        //event.preventDefault();
        localStorage.removeItem('current_session');
        document.getElementById('logout-form').submit();
    }

    /**
     * Determines whether the user is on the group page
     *
     * @returns {boolean} true if is on the group page, otherwise false
     */
    isGroupsPage() {
        return this.getRoute() === 'groups';
    }

    render() {
        if(this.state.screenLoader==0){
            return (
                <div className="container-scroller">
                    <div className="horizontal-menu">
                        <nav className="navbar top-menu top-navbar col-lg-12 col-12 p-0">
                            <div className="nav-top flex-grow-1">
                                <div className="container d-flex flex-row h-100 align-items-center">
                                    <div className="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                                        <a className="navbar-brand brand-logo" href="/">
                                            <img src="/asset/images/logo.png" className="big-logo" alt="logo" />
                                            <img src="/asset/images/groupkit_mobile_logo.png" className="small-logo" alt="profile"/>
                                        </a>
                                    </div>
                                    <div className="navbar-menu-wrapper d-flex align-items-center justify-content-end flex-grow-1">
                                        <ul className="navbar-nav navbar-nav-right">
                                            <li className="nav-item nav-profile dropdown">
                                                <img src="/asset/images/groupkit_mobile_logo.png" alt="profile"/>
                                                <span className="menu-title profile_title cursor-pointer show-dropdwon" onClick={this.setDropDown.bind(this, 1)}>
                                                    Hello, { this.state.user.name}</span>
                                                <a className="nav-link dropdown-toggle cursor-pointer show-dropdwon" id="profileDropdown" onClick={this.setDropDown.bind(this, 1)}></a>
                                                <div className="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown" style={ this.state.dropDown ? {display:'block'} : {} }>
                                                    {this.state.user.can_have_team ?
                                                    <a className="dropdown-item" href={API.url('teamMembers')} > <i className="fa fa-users"></i>Team Members</a>
                                                    : ''}
                                                    <a className="dropdown-item" href={API.url('giveaway')}> <i className="fa fa-trophy"></i>Giveaway</a>
                                                    <a className="dropdown-item" href={API.url('setting')}>
                                                        <i className="fa fa-gear"></i>Settings
                                                    </a>
                                                    <div className="dropdown-divider"></div>
                                                    <a className="dropdown-item" onClick={this.logOut.bind(this)}>
                                                        <i className="fa fa-sign-out"></i>Logout
                                                    </a>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </nav>
                    </div>
                    {this.state.screen==0 ?
                    (<Home
                        setScreen={this.setScreen.bind(this)}
                        getUser={this.getUser.bind(this)}
                        getGroups={this.getGroups.bind(this)}
                        groupDetails={this.groupDetails.bind(this)}
                        groupsFilter={this.groupsFilter.bind(this)}
                        removeResponderFromGroup = {this.removeResponderFromGroup.bind(this)}
                        removeGroup = {this.removeGroup.bind(this)}
                        groups={this.state.groups}
                        todays_emails_added_count={this.state.todaysEmailsAddedCount}
                        todays_members_count={this.state.todaysMembersCount}
                        weeks_emails_added_count={this.state.weeksEmailsAddedCount}
                        weeks_members_count={this.state.weeksMembersCount}
                        user={this.state.user}
                        onBuildMembersCSV={this.downloadMembersCSV.bind(this)}
                        onToggleGroupCSVDownloadStatus={this.toggleGroupCSVDownloadStatus.bind(this)}
                    />)
                    :
                        (<Details
                            setScreen={this.setScreen.bind(this)}
                            filterMember={this.filterMember.bind(this)}
                            backToMain={this.backToMain.bind(this)}
                            groupsDetails={this.state.groupsDetails}
                            groups={this.state.groups}
                            membersFound={this.state.membersFound}
                            user={this.state.user}
                            onBuildMembersCSV={this.downloadMembersCSV.bind(this)}
                        />)
                    }
                    <footer className="footer">
                        <div className="w-100 clearfix text-center">
                            <img height="80" src="/asset/images/logo.png" alt="logo" />
                            <p className="mt-1">
                                <a  target="_blank" href="https://groupkit.com/privacy?=">Privacy </a>|
                                <a  target="_blank" href="https://groupkit.com/terms?="> Terms </a>|
                                <a  target="_blank" href="https://members.groupkit.com/login"> Training Platform </a>|
                                <a  target="_blank" href="https://groupkit.tapfiliate.com/"> Affiliates </a>|
                                <a  target="_blank" href="https://support.groupkit.com/"> Support </a>
                            </p>
                            <p className="mt-1">©{moment(new Date()).format("YYYY")} SME Publishing, LLC / All Rights Reserved.</p>
                            <p className="mt-1">GroupKit is not affiliated by Facebook™ in any way. Facebook™ is a registered trademark Facebook Inc.</p>
                        </div>
                    </footer>
                </div>
            );
        }else{
            return(
                <div className="loader-demo-box sectionLoader">
                    <div className="bar-loader">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            )
        }
    }
}
export default Main;
