import React, { Component } from 'react';
import {API} from '../Api'
import Select from 'react-select';
import CryptoJS from 'crypto-js';

class Aweber extends Component{
    constructor(props) {
        super(props);
        this.state={
            oneTime:false,
            selectGroupId:'',
            activeUserId:'',
            AutoResponderError:'',
            AutoResponderSuccess:'',
            aweber:{
                client_id:'uN922fsr2kQDjN2R2SIcW2NWsb3WbaCG',
                access_token:"",
                refresh_token:"",
                account_id:""
            },
            lists:[],
            activeList:'',
            auth_code:'',
            code_verifier:''
        }
    }
    componentDidMount(){
        var response=this.props.setActiveAutoresponder('Aweber')
        if(response.account_id && response.activeList){
            this.setState({
                aweber:{
                    access_token:response.access_token,
                    refresh_token:response.refresh_token,
                    account_id:response.account_id,
                    client_id:response.client_id,
                },
                activeList:response.activeList,
                selectGroupId:response.group_id,
                activeUserId:response.user_id
            })
            setTimeout(function(){
               this.getList()
            }.bind(this),100)
        }else{
            this.setState({
                selectGroupId:response.group_id,
                activeUserId:response.user_id,
            })
        }
        /** Aweber Token */
        window.addEventListener("message", function(event) {
            if(event.data !=undefined && event.data.type=="aweberlogin" && event.data.type !=undefined && event.data.code !=undefined){
                this.setState({auth_code:event.data.code})
            }
        }.bind(this));
    }
    componentWillReceiveProps(props) {}
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
            responder_type:'Aweber',
            responder_json:{
                activeList:this.state.activeList,
                access_token:this.state.aweber.access_token,
                refresh_token:this.state.aweber.refresh_token,
                account_id:this.state.aweber.account_id,
                client_id:this.state.aweber.client_id,
            },
            group_id:this.state.selectGroupId,
            user_id:this.state.activeUserId,
            is_check:0,
        }
        API.save_autoresponder(obj).then((res) => {
            if(res.data.code==200){
                this.successMessage(res.data.message)
                this.props.getGroups()
                this.props.showRemoveAutoresponder()
            }else{
                this.errorMessage(res.data.message)
            }
        }).catch((error) => {
            //console.log(error)
        });

    }
    /* Aweber*/
    handleChang(param,event){
        this.setState({[param]:event.target.value});
    }
    selectActiveLists(param,event){
        this.setState({[param]:event});
    }
    //

    randomkey(len) {
        var maxlen = 8,
            min = Math.pow(16, Math.min(len, maxlen) - 1)
        var max = Math.pow(16, Math.min(len, maxlen)) - 1,
            n = Math.floor(Math.random() * (max - min + 1)) + min,
            r = n.toString(16);
        while (r.length < len) {
            r = r + this.randomkey(len - maxlen);
        }
        return r;
    }
    hexToBase64urlsafe(hexstring) {
        return btoa(hexstring.match(/\w{2}/g).map(function(a) {
            return String.fromCharCode(parseInt(a, 16));
        }).join("")).replace(/\+/g, '-').replace(/\//g, '_').replace(/\=+$/, '');
    }
    aweberlogin(){
        var code_verifier = this.hexToBase64urlsafe(this.randomkey(64));
        var savegeneratedkey = this.hexToBase64urlsafe(CryptoJS.SHA256(code_verifier).toString(CryptoJS.enc.Hex));
        this.setState({code_verifier:code_verifier})
        var urlparams = {};
        urlparams.response_type = "code";
        urlparams.client_id = this.state.aweber.client_id; // aweber client id
        urlparams.redirect_uri = "urn:ietf:wg:oauth:2.0:oob";
        urlparams.code_challenge = savegeneratedkey;
        urlparams.code_challenge_method = "S256";
        urlparams.state = Date.now();
        urlparams.scope = "account.read list.read list.write subscriber.read subscriber.write subscriber.read-extended";
        var url = "https://auth.aweber.com/oauth2/authorize?" + new URLSearchParams(urlparams).toString();
        window.open(url)
    }
    async getAweberDetails(){
        var object={}
        object.auth_code=this.state.auth_code
        object.client_id=this.state.aweber.client_id
        object.code_verifier=this.state.code_verifier
        await this.getAweberToken(object)
        await this.getAweberAccount()
        await this.getAweber()
    }
    getAweberToken(object){
        return new Promise(resolve => {
            API.aweberToken(object).then((res) => {
                if(res.data.code==200){
                    var object=this.state.aweber
                    object['access_token']=res.data.data.access_token
                    object['refresh_token']=res.data.data.refresh_token
                    this.setState({aweber:object})
                    resolve(true);
                }else{
                    this.errorMessage(res.data.message)
                    resolve(false);
                }
            }).catch((error) => {
                this.errorMessage('Invalid Request')
                resolve(false);
            });
        })
    }
    getAweberAccount(){
        return new Promise(resolve => {
            API.aweberAcount(this.state.aweber).then((res) => {
                if(res.data.code==200){
                    var object=this.state.aweber
                    if(res.data.data.entries[0] !=undefined && res.data.data.entries.length){
                        object['account_id']=res.data.data.entries[0].id
                        this.setState({aweber:object})
                        resolve(true);
                    }else{
                        this.errorMessage('Invalid Request')
                        resolve(false);
                    }
                }else{
                    this.errorMessage(res.data.message)
                    resolve(false);
                }
            }).catch((error) => {
                this.errorMessage('Invalid Request')
                resolve(false);
            });
        });
    }
    async getList(){
        await this.getAweber()
    }
    getAweber(){
        return new Promise(resolve => {
            var sendParams=this.state.aweber
            sendParams.group_id=this.state.selectGroupId
            API.aweber(sendParams).then((res) => {
                if(res.data.code == 401){
                    swal({
                        text: "Your integration has expired. Please setup again.",
                        icon: "warning",
                        buttons: true,
                        dangerMode: true,
                    }).then((willDelete) => {
                        this.props.removeAutoresponderApi();
                    })
                }
                if(res.data.code==200){
                    this.setState({
                        lists:res.data.data.list,
                    });
                    resolve(true)
                }else{
                    this.errorMessage(res.data.message)
                    resolve(false)
                }
            }).catch((error) => {
                //console.log(error)
                resolve(false)
            });
        });
    }
    render() {
        return (
            <div className="">
                <form className="forms-sample" id="autoresponder_from">
                    <div style={this.state.auth_code ? {display:'block'} : {display:'none'}}>
                        <label className="col-sm-12 col-form-label pl-0">Enter Your Aweber AUTH Code</label>
                        <div className="form-group row">
                            <div className="col-sm-12">
                                <input disabled={this.state.auth_code ? 'disabled' : ''} type="text" className="form-control"  placeholder="Aweber AUTH Code" value={this.state.auth_code} onChange={this.handleChang.bind(this,"auth_code")} />
                            </div>
                        </div>
                    </div>
                    <div className="form-group col-sm-12 p-0" style={this.state.lists.length ? {display:'block'} : {display:'none'}}>
                        <label className="col-sm-12 pl-0">Select Your List</label>
                        <Select
                            className="col-sm-12 p-0"
                            value={this.state.activeList}
                            onChange={this.selectActiveLists.bind(this,'activeList')}
                            options= { this.state.lists.map((e, key) => {
                                return { label:e.name, value:e.id}
                            })}
                        />
                    </div>
                    {/** Check Token and List */}
                    <div className="form-group row" style={this.state.activeList ? {display:'none'} : {display:'block'}}>
                        <div className="col-sm-12">
                            <button type="button" className="btn btn-primary" style={this.state.auth_code ? {display:'block'} : {display:'none'}} onClick={this.getAweberDetails.bind(this)} disabled={this.state.activeList ? 'disabled' : ''}>
                                CHECK to proceed
                            </button>
                            <button type="button" className="btn btn-primary" style={this.state.auth_code=='' && this.state.aweber.account_id=='' ? {display:'block'} : {display:'none'}} onClick={this.aweberlogin.bind(this)} disabled={this.state.activeList ? 'disabled' : ''}>
                                Login
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
export default Aweber;
