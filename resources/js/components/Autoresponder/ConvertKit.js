import React, { Component } from 'react';
import {API} from '../Api'
import Select from 'react-select';
class ConvertKit extends Component{
    constructor(props) {
        super(props);
        this.state={
            oneTime:false,
            selectGroupId:'',
            activeUserId:'',
            AutoResponderError:'',
            AutoResponderSuccess:'',
            convertKit: {
                api_key: '',
                api_secret: '',
            },
            lists:[],
            activeList:''
        }
    }
    componentDidMount(){
        var response=this.props.setActiveAutoresponder('ConvertKit')
        if(response.api_key && response.activeList){
            this.setState({
                convertKit: {
                    api_key: response.api_key,
                    api_secret: response.api_secret ?? '',
                },
                activeList:response.activeList,
                selectGroupId:response.group_id,
                activeUserId:response.user_id
            })
            setTimeout(function(){
                this.getConvertKit()
            }.bind(this),100)
        }else{
            this.setState({
                selectGroupId:response.group_id,
                activeUserId:response.user_id,
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
            responder_type:'ConvertKit',
            responder_json:{
                activeList: this.state.activeList,
                api_key: this.state.convertKit.api_key,
                custom_labels: [
                    'LAST NAME',
                ],
                custom_labels_mapper: [
                    {
                        label: 'LAST NAME',
                        member_field: 'l_name',
                    },
                ],
            },
            group_id: this.state.selectGroupId,
            user_id: this.state.activeUserId,
            is_check: 0,
        }

        if (this.state.convertKit.api_secret) {
           obj.responder_json.api_secret = this.state.convertKit.api_secret;
        }

        API.save_autoresponder(obj).then((res) => {
            if(res.data.code==200){
                this.successMessage(res.data.message);
                this.props.getGroups();
                this.props.showRemoveAutoresponder()
            }else{
                this.errorMessage(res.data.message)
            }
        }).catch((error) => {
            //console.log(error)
        });

    }
    /* convertKit */
    handleChang(param,event){
        this.state.convertKit[param]=event.target.value
        this.setState({convertKit:this.state.convertKit});
    }
    selectActiveLists(param,event){
        this.setState({[param]:event});
    }

    getConvertKit(){
        API.getconvertkit(this.state.convertKit).then((res) => {
            if(res.data.code==200){
                this.setState({
                    lists:res.data.data.list,
                });
            }else{
                this.errorMessage(res.data.message)
            }
        }).catch((error) => {
            //console.log(error)
        });
    }
    render() {
        return (
            <div className="">
                <form className="forms-sample" id="autoresponder_from">
                    <label className="col-sm-12 col-form-label pl-0">
                        Enter Your ConvertKit API Key & API Secret Key
                    </label>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="ConvertKit API Key"
                                value={this.state.convertKit.api_key}
                                onChange={this.handleChang.bind(this, 'api_key')}
                            />
                        </div>
                    </div>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="ConvertKit API Secret"
                                value={this.state.convertKit.api_secret}
                                onChange={this.handleChang.bind(this, 'api_secret')}
                            />
                        </div>
                    </div>
                    <div className="form-group col-sm-12 p-0" style={this.state.lists.length ? {display:'block'} : {display:'none'}}>
                        <label className="col-sm-12 pl-0">Select Your Form</label>
                        <Select
                            className="col-sm-12 p-0"
                            value={this.state.activeList}
                            onChange={this.selectActiveLists.bind(this,'activeList')}
                            options= { this.state.lists.map((e, key) => {
                                return { label:e.name, value:e.id}
                            })}
                        />
                    </div>
                    <div className="form-group row" style={this.state.activeList ? {display:'none'} : {display:'block'}}>
                        <div className="col-sm-12">
                            <button type="button" className="btn btn-primary" onClick={this.getConvertKit.bind(this)} disabled={this.state.activeList ? 'disabled' : ''}>
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
export default ConvertKit;
