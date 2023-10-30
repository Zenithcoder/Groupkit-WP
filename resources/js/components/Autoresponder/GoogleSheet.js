import React, { Component } from 'react';
import {API} from '../Api';
import Select from "react-select";

/**
 * Date add time options for GoogleSheet dateAddTimeFormat
 *
 * @type {[{label: string, value: string}, {label: string, value: string}, {label: string, value: string}, {label: string, value: string}]}
 */
const DATE_ADD_TIME_FORMAT_OPTIONS = [
    { value: 'c', label: 'ISO-8601 Format (Zapier)' },
    { value: 'j-n-Y G:i:s', label: 'Day-Month-Year' },
    { value: 'n-j-Y G:i:s', label: 'Month-Day-Year' },
    { value: 'Y-n-j G:i:s', label: 'Year-Month-Day' },
];

class GoogleSheet extends Component{
    constructor(props) {
        super(props);
        this.state={
            oneTime:false,
            selectGroupId:'',
            activeUserId:'',
            AutoResponderError:'',
            AutoResponderSuccess:'',
            googleSheet:{
                sheetURL:'',
                token:'',
                refreshToken: '',

                /**
                 * @type {Object} as {label: string, value: string} date format for changing `date_add_time` field
                 * in the database
                 */
                dateAddTimeFormat: {value: '', label: ''},
            },
            lists:[],
            activeList:'',
            screenLoader: 1,

            /**
             * Indicates if the integration is fresh or not.
             *
             * @type boolean true if is new integration, otherwise false
             */
            newIntegration: false,
        }
    }
    componentDidMount(){
        var response=this.props.setActiveAutoresponder('GoogleSheet')
        if(response.sheetURL && response.activeList){
            // GoogleSheet integration is connected to the customer group - edit purpose
            this.setState({
                googleSheet:{
                    sheetURL:response.sheetURL,
                    token:response.token,
                    refreshToken: response.refreshToken,
                    dateAddTimeFormat: DATE_ADD_TIME_FORMAT_OPTIONS
                                          .find(option => option.value === response.dateAddTimeFormat),
                },
                activeList:response.activeList,
                selectGroupId:response.group_id,
                activeUserId:response.user_id
            })

            setTimeout(function(){
                this.getGoogleSheet();
            }.bind(this),100)
        }else{
            // GoogleSheet integration is not already connected to the customer group - fresh integration
            this.changeDateAddFormat(DATE_ADD_TIME_FORMAT_OPTIONS[0]); // set first option as default for new integration
            this.setState({
                selectGroupId:response.group_id,
                activeUserId:response.user_id,
                newIntegration: true,
            })
        }
    }
    successMessage(message){
        this.setState({AutoResponderSuccess:message})
        setTimeout(function(){
            this.setState({AutoResponderSuccess:''})
        }.bind(this),3000)
    }
    errorMessage(message){
        this.setState({AutoResponderError:message})
        setTimeout(function(){
            this.setState({AutoResponderError:''})
        }.bind(this),3000)
    }
    saveAutoresponder(){
        var obj={
            responder_type:'GoogleSheet',
            responder_json:{
                activeList: this.state.activeList,
                sheetURL: this.state.googleSheet.sheetURL,
                token: this.state.googleSheet.token,
                refreshToken: this.state.googleSheet.refreshToken,
                dateAddTimeFormat: this.state.googleSheet.dateAddTimeFormat.value,
            },
            group_id:this.state.selectGroupId,
            user_id:this.state.activeUserId,
            is_check:0,
        }
        API.save_autoresponder(obj).then((res) => {
            if(res.data.code==200){
                this.successMessage(res.data.message);
                this.props.getGroups()
                this.props.showRemoveAutoresponder()

                if (this.state.newIntegration) {
                    // since we are doing this in background we are silencing response
                    // todo: after moving connecting google sheet to GoogleSheetService, move this to BE
                    API.sendHeadersToGoogleSheet({ group_id: this.state.selectGroupId }).then();
                }
            }else{
                this.errorMessage(res.data.message)
            }
        }).catch((error) => {
            //console.log(error)
        });

    }
    /* GoogleSheet*/
    handleChang(param,event){
        this.state.googleSheet[param]=event.target.value
        this.setState({googleSheet:this.state.googleSheet});
    }
    selectActiveLists(param,event){
        this.setState({[param]:event});
    }
    async getGoogleSheet(){
        if(this.state.googleSheet.sheetURL){
            if (this.state.googleSheet.token && this.state.googleSheet.refreshToken) {
                var token = await this.refreshTokenApi();
                this.state.googleSheet['token']=token
                this.setState({googleSheet:this.state.googleSheet});
            }else{
                var token = await this.returntokenvalue();
            }
            if (token != "") {
                var sheetID = new RegExp("/spreadsheets/d/([a-zA-Z0-9-_]+)").exec(this.state.googleSheet.sheetURL);
                if(sheetID==null){
                    this.errorMessage("Please Enter The Valid Google Sheet URL")
                    return false;
                }
                this.setState({ activeList: 1 });
            } else {
                this.errorMessage("Please login with your Google account");
                return false;
            }
        }else{
            this.errorMessage("Please Enter The Valid Google Sheet URL")
            return false;
        }
    }
    refreshTokenApi(){
        return new Promise(function(resolve) {
            API.refresh_google_token(this.state.selectGroupId).then((res) => {
                if(res.data.code==200){
                    resolve(res.data.data)
                }else{
                    resolve('')
                }
            }).catch((error) => {
                resolve('')
            });
        }.bind(this));
    }
    returntokenvalue() {
        var self=this
        return new Promise(function(resolve) {
            var url = API.url('gmailAuth');
            var width = 760;
            var height = 800;
            var left = (screen.width/2)-(width/2);
            var top = (screen.height/2)-(height/2);
            window.open(url,"myWindow","width="+width+",height="+height+",top="+top+", left="+left);
            var bc = new BroadcastChannel('groupkit_channel');
            bc.onmessage = function(event) {
                if(event.data !=undefined && event.data.type=="sendGmailToken" && event.data.type !=undefined){
                    var response=event.data.data
                    if(response) {
                        try{
                            var token=''
                            if( response.data !=null &&
                                response.data !=''&&
                                response.code === 200 &&
                                response.data.token &&
                                response.data.refreshToken){

                                var sheetData=self.state.googleSheet;
                                sheetData['token']=response.data.token
                                sheetData['refreshToken']=response.data.refreshToken
                                self.setState({googleSheet:sheetData})
                                token=response.data.token
                                resolve(token)
                            }else{
                                resolve(token)
                            }
                        }catch(e){}
                    }
                }
            }
        });
    }

    /**
     * Sets {@see GoogleSheet.state.googleSheet.dateAddTimeFormat} on select change
     *
     * @param {Object} dateAddTimeFormat as { value: String, label: String} selected from the {@see DATE_ADD_TIME_FORMAT_OPTIONS}
     */
    changeDateAddFormat(dateAddTimeFormat) {
        this.state.googleSheet.dateAddTimeFormat = dateAddTimeFormat;

        this.setState({ googleSheet: this.state.googleSheet });
    }

    render() {
        return (
            <div className="">
                <form className="forms-sample" id="autoresponder_from">
                    <label className="col-sm-12 col-form-label pl-0">Connect GroupKit To Google Sheet</label>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input
                                type="text"
                                className="form-control mb-2"
                                placeholder="Google Sheet URL"
                                disabled={this.state.googleSheet.token ? 'disabled' : ''}
                                value={this.state.googleSheet.sheetURL}
                                onChange={this.handleChang.bind(this, 'sheetURL')}
                            />
                        </div>
                    </div>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <Select
                                isMulti={false}
                                value={this.state.googleSheet.dateAddTimeFormat}
                                onChange={this.changeDateAddFormat.bind(this)}
                                options={DATE_ADD_TIME_FORMAT_OPTIONS}
                                placeholder={'Select date time format'}
                            />
                        </div>
                    </div>
                    <div className="form-group row" style={this.state.activeList ? {display:'none'} : {display:'block'}}>
                        <div className="col-sm-12">
                            <button type="button" className="btn btn-primary" onClick={this.getGoogleSheet.bind(this)} disabled={this.state.activeList ? 'disabled' : ''}>
                                CHECK
                            </button>
                        </div>
                    </div>
                    <div className="form-group row" style={this.state.activeList ? {display:'block'} : {display:'none'}}>
                        <div className="col-sm-12">
                            <button type="button" className="btn btn-primary" onClick={this.saveAutoresponder.bind(this)}>
                                Save Integration
                            </button>
                        </div>
                    </div>
                    <div className="alert alert-fill-danger" role="alert" style={this.state.AutoResponderError ? {display:'block'} : {display:'none'}}>
                        <span>{this.state.AutoResponderError}</span>
                    </div>
                    <div className="alert alert-fill-success" role="alert" style={this.state.AutoResponderSuccess ? {display:'block'} : {display:'none'}}>
                        <span>{this.state.AutoResponderSuccess}</span>
                    </div>
                </form>
            </div>
        );
    }
}
export default GoogleSheet;
