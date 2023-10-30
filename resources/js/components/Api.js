import axios from 'axios';

var APPURL=document.querySelector('#app_url').value;
var APIURL=APPURL+'/api';

export class API {
    static integrationAutoresponderLink(key){
        var integrationLinks={
            'Aweber':'https://support.groupkit.com/article/6-how-to-integrate-groupkit-with-aweber/',
            'ActiveCampaign':'https://support.groupkit.com/article/8-how-to-integrate-groupkit-with-activecampaign',
            'ConvertKit':'https://support.groupkit.com/article/9-how-to-integrate-groupkit-with-convertkit/',
            'Getresponse':'https://support.groupkit.com/article/7-how-to-integrate-groupkit-with-getresponse',
            'GoHighLevel':'https://support.groupkit.com/article/35-how-to-integrate-groupkit-with-gohighlevel',
            'GoogleSheet':'https://support.groupkit.com/article/10-how-to-integrate-groupkit-with-google-sheets-zapier',
            'Kartra':'https://support.groupkit.com/article/41-how-to-integrate-groupkit-with-kartra',
            'MailChimp':'https://support.groupkit.com/article/34-how-to-integrate-groupkit-with-mailchimp',
            'Mailerlite':'https://support.groupkit.com/article/42-how-to-integrate-groupkit-with-mailerlite',
            'OntraPort': 'https://support.groupkit.com/article/43-how-to-integrate-groupkit-with-ontraport',
            'InfusionSoft': 'https://support.groupkit.com/article/45-how-to-integrate-groupkit-with-keap-infusionsoft',
        };
        return integrationLinks[key]
    }
    static url(path) {
        return APPURL+'/'+path
    }
    static CurlCall(endPoint,Parm,get) {
        const authToken = API.getToken();

        if(get){
            return axios(APIURL+endPoint, {
            method: 'GET',
            withCredentials: true,
            headers: {
                "Accept": "application/json",
                "Authorization": "Bearer " + authToken
            },
            params: Parm,
            }).then((res) => {
                return res;
            }).catch((error) => {
                return error;
            });
        }else{
            return axios(APIURL+endPoint, {
            method: 'POST',
            withCredentials: true,
            headers: {
                "Accept": "application/json",
                "Authorization": "Bearer " + authToken
            },
            data:Parm,
            }).then((res) => {
                return res;
            }).catch((error) => {
                return error;
            });
        }
    }
    static getUser(){
        return this.CurlCall('/getUser',[],true)
    }
    static groups(){
        return this.CurlCall('/getGroups',[],true)
    }
    static groupsByID(id){
        return this.CurlCall('/groupsByID/'+id,[],true)
    }
    static groupsDetails(id){
        return this.CurlCall('/groups/'+id,[],true)
    }

    /**
     * Call endpoint that store changes about columns visibility.
     *
     * @param {{ columnsVisibility: object, groupId: integer }} parameters object that
     * contains data about columns visibility and the Facebook group ID.
     *
     * @returns {Promise} AxiosPromise which accepts a response with the data from
     * the endpoint whether the sent data was successfully saved or not.
     */
    static setColumnsVisibility(parameters) {
        return this.CurlCall('/groups/setColumnsVisibility', parameters, false);
    }

    /**
     * Call endpoint to get the state for columns visibility.
     *
     * @param {integer} groupId ID of the Facebook group.
     *
     * @returns {Promise} AxiosPromise which accepts a response with the data
     * about the Facebook group columns visibility settings, and whether they
     * exists in the DB or not.
     */
    static getColumnsVisibility(groupId) {
        return this.CurlCall(`/groups/getColumnsVisibility/${groupId}`, [], true);
    }

    /**
     * Call endpoint to get the group settings
     *
     * @param {integer} groupId ID of the Facebook group.
     *
     * @returns {Promise} The promise of the HttpRequest completion
     */
    static getGroupSettings(groupId) {
        return this.CurlCall(`/groups/settings/${groupId}`, [], true);
    }

    /**
     * Call endpoint that store changes about columns visibility.
     *
     * @param {{ columnsVisibility: object, groupId: integer }} parameters object that
     * contains data about columns visibility and the Facebook group ID.
     *
     * @returns {Promise} AxiosPromise which accepts a response with the data from
     * the endpoint whether the sent data was successfully saved or not.
     */
    static setColumnsWidth(parameters) {
        return this.CurlCall('/groups/columns-width/', parameters, false);
    }

    static getGroupsTag(id){
        return this.CurlCall('/getGroupsTag/'+id,[],true)
    }
    static deleteGroup(id){
        return this.CurlCall('/groupsDelete/'+id,[],true)
    }
    static updateMember(parm){
        return this.CurlCall('/memberUpdate',parm,false)
    }
    static filterMember(parm){
        return this.CurlCall('/member',parm,false)
    }

    /**
     * Fetches the tags for the selected members wth AJAX call from '/api/members/getMembersTagsList' endpoint.
     *
     * @param {{ tags_to_add: array, recommended_tags_to_add: array, group_id: number, selected_member_ids: array,
     * excluded_member_ids: array, is_cross_pagination_selection: boolean, tags_to_delete: *, recommended_tags_to_delete: *,
     * startDate: string, endDate: string, tags: string, autoResponder: string, page: number, perPage: number,
     * searchText: string, sort: {sortName: string, sortOrder: string} }} parameters
     *
     * @return {Promise<AxiosResponse>}
     */
    static getMembersTagsList(parameters) {
        return this.CurlCall('/members/getMembersTagsList', parameters, false);
    }

    /**
     * Execute AJAX call to remove members endpoint
     *
     * @param {{excluded_member_ids: *[], group_id, is_select_all_in_group: (boolean|*), selected_member_ids: *[]}} parameters
     *
     * @return {Promise<AxiosResponse>}
     */
    static removeGroupMember(parameters){
        return this.CurlCall('/removeGroupMembers', parameters, false);
    }
    /** Save Groups */
    static saveRecored(parm){
        return this.CurlCall('/recordSave',parm,false)
    }
    /** import & import csv */
    static importFile(parm){
        return this.CurlCall('/groups/import',parm,false)
    }

    /**
     * Uploads Group member data in CSV format to the cloud app
     *
     * @param {object} csvData - Group member data from the Facebook group application form
     * @returns {Promise} The promise of the HttpRequest completion
     */
    static importCsvFile(csvData){
        return this.CurlCall('/groups/importCsv', csvData,false)
    }

    /**
     * Gets members data for the provided Facebook group id
     *
     * @param {Object} params with filters
     *
     * @returns {Object} containing formatted Facebook group members and name of the CSV file on success,
     *                   otherwise the error
     */
    static buildGroupMembersCsvFile(params) {
        return this.CurlCall('/members/buildCsv', params, false)
    }

    /* saveAutoresponder */
    static save_autoresponder(parm){
        return this.CurlCall('/saveAutoresponder',parm,false)
    }
    static deleteAutoresponder(parm){
        return this.CurlCall('/deleteAutoresponder',parm,false)
    }

    /* ActiveCampaign API */
    static activeCampaign(parm){
        return this.CurlCall('/activeCampaign',parm,false)
    }

    /* mailerlite */
    static mailerlite(parm){
        return this.CurlCall('/mailerlite',parm,false)
    }

    /* getresponse */
    static getresponse(parm){
        return this.CurlCall('/getresponse',parm,false)
    }

    /* ConvertKit */
    static getconvertkit(parm){
        return this.CurlCall('/getConvertKit',parm,false)
    }

    /* MailChimp */
    static getMailchimp(parm){
        return this.CurlCall('/getMailchimp',parm,false)
    }

    /* GoHighLevel */
    static gohighlevel(parm){
        return this.CurlCall('/getGoHighLevel',parm,false)
    }

    /* Kartra */
    static kartra(parm){
        return this.CurlCall('/getKartra',parm,false)
    }

    /* Aweber */
    static aweberToken(parm){
        return this.CurlCall('/getToken',parm,false)
    }
    static refreshToken(parm){
        return this.CurlCall('/getRefreshToken',parm,false)
    }
    static aweberAcount(parm){
        return this.CurlCall('/getAweberAccount',parm,false)
    }
    static aweber(parm){
        return this.CurlCall('/getaweber',parm,false)
    }

    /** GoogleSheet */
    static refresh_google_token(parm){
        return this.CurlCall('/googleRefreshToken/'+parm,[],true)
    }

    /* ontraPort API */
    static ontraPort(parm) {
        return this.CurlCall('/ontraPort', parm, false);
    }

    /**
     * Calling InfusionSoft API for contact synchronization
     *
     * @param {Object} param containing access token, authorize code, client id, client secret, refresh token.
     */
    static InfusionSoft(param) {
        return this.CurlCall('/infusionSoft', param, false);
    }

    /**
     * Gets API authentication token
     *
     * @return {string|null} containing access_token if is it accessible in local storage, otherwise null
     */
    static getToken() {
        if (localStorage.getItem('current_session')) {
            return JSON.parse(atob(localStorage.getItem('current_session'))).access_token;
        }
        return null;
    }

    /**
     * Send the group member id to integration API
     *
     * @param   {Object} $requestObject containing group member ids
     *
     * @returns {Object} with success response if group members are successfully sent,
     *                   otherwise error response with the message
     */
    static sendToIntegrationApi($requestParam)
    {
        return this.CurlCall('/sendToIntegration', $requestParam, false)
    }

    /**
     * Sends members to the add group members API
     *
     * @param {Object} requestData with Facebook group and Facebook group members
     * @return {Promise} The promise of the HttpRequest completion
     */
    static addMembers(requestData) {
        return this.CurlCall('/groups/addMembers', requestData, false);
    }

    /**
     * Get members names from the endpoint that will be used for
     * populating modal the Generate Facebook Tags.
     *
     * @param {{ excludedMemberIds: Array<number>, groupId: number }} parameters
     *
     * @returns {Promise} The promise of the HttpRequest completion
     */
    static getMembersNames(parameters) {
        return this.CurlCall('/getMembersNames', parameters, false);
    }

    /**
     * Checks the status of the provided asynchronous job ids
     *
     * @param {int[]} jobIds
     *
     * @return {Promise<AxiosResponse<Object.<int,bool>>>}
     */
    static checkJobs (jobIds) {
        return this.CurlCall('/checkJobs', { job_ids: jobIds }, true);
    }

    /**
     * Sends tags_to_add, tags_to_delete with group members to
     * bulk manage tags for provided members API
     *
     * @param {object} requestData including members ids, and optionally:
     * tags to add, tags to delete, recommended tags to add, recommended tags to delete
     *
     * @return {Promise} The promise of the HttpRequest completion
     */
    static sendTagsToTheGroupMembers(requestData) {
        return this.CurlCall('/members/bulkManageTags', requestData, false);
    }

    /**
     * Send headers to the Google Sheet
     *
     * @param {object} requestData including group_id
     *
     * @return {Promise} The promise of the HttpRequest completion
     */
    static sendHeadersToGoogleSheet(requestData) {
        return this.CurlCall('/google-sheet/send-headers', requestData, false);
    }
}
