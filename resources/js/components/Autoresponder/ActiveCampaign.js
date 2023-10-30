import React, { Component } from 'react';
import {API} from '../Api'
import Select from 'react-select';
class ActiveCampaign extends Component{
    constructor(props) {
        super(props);
        this.state={
            oneTime:false,
            selectGroupId:'',
            activeUserId:'',
            AutoResponderError:'',
            AutoResponderSuccess:'',
            activeCampaign:{
                host_name:'',
                api_key:''
            },
            lists:[],
            tags:[],
            activeList:'',
            activeTags:''
        }
    }
    componentDidMount(){
        var response=this.props.setActiveAutoresponder('ActiveCampaign')
        if(response.api_key && response.host_name){
            this.setState({
                activeCampaign:{
                    host_name:response.host_name,
                    api_key:response.api_key
                },
                activeList:response.activeList,
                activeTags:response.activeTags,
                selectGroupId:response.group_id,
                activeUserId:response.user_id
            })
            setTimeout(function(){
                this.getActiveCampaign()
            }.bind(this),100)
        }else{
            this.setState({
                selectGroupId:response.group_id,
                activeUserId:response.user_id,
            })
        }
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
            responder_type:'ActiveCampaign',
            responder_json:{
                activeList:this.state.activeList,
                activeTags:this.state.activeTags,
                host_name:this.state.activeCampaign.host_name,
                api_key:this.state.activeCampaign.api_key
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

    /* ActiveCampaign*/
    handleChange(param,event){
        this.state.activeCampaign[param]=event.target.value
        this.setState({activeCampaign:this.state.activeCampaign});
    }

    selectActiveLists(param,event){
        this.setState({[param]:event});
    }

    setActiveList(){
        /** activeList */
        var value = this.state.activeList.value;
        if(this.state.activeList.label==''){
            var list = this.state.lists.map((key)=>{
                if(key.id==value){
                    return {label: key.name, value: key.id}
                }
            }).filter(function( data ) {return data !== undefined; })
            if(list.length){
                this.setState({activeList:list[0]})
            }
        }

        /** tags List */
        var tags = this.state.activeTags.value;
        if(this.state.activeTags.label==''){
            var tagsList = this.state.tags.map((key)=>{
                if(key.id==value){
                    return {label: key.tag, value: key.id}
                }
            }).filter(function( data ) {return data !== undefined; })
            if(tagsList.length){
                this.setState({activeTags:tagsList[0]})
            }
        }
    }

    getActiveCampaign(){
        API.activeCampaign(this.state.activeCampaign).then((res) => {
            if(res.data.code==200){
                this.setState({
                    lists:res.data.data.list,
                    tags:res.data.data.tags
                });
                this.setActiveList();
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
                    <label className="col-sm-12 col-form-label pl-0">Enter Your ActiveCampaign Username & API Key</label>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input type="text" className="form-control"  placeholder="ActiveCampaign Username" value={this.state.activeCampaign.host_name} onChange={this.handleChange.bind(this,"host_name")} />
                        </div>
                    </div>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input type="text" className="form-control"  placeholder="ActiveCampaign API Key" value={this.state.activeCampaign.api_key} onChange={this.handleChange.bind(this,"api_key")} />
                        </div>
                    </div>
                    <div className="form-group col-sm-12 p-0" style={this.state.lists.length ? {display:'block'} : {display:'none'}}>
                        <label className="col-sm-12 pl-0">Select Your List</label>
                        <Select
                            className="col-sm-12 p-0"
                            value={this.state.activeList}
                            onChange={this.selectActiveLists.bind(this,'activeList')}
                            options= { this.state.lists.map((e, key) => {
                                if(e.name)
                                return { label:e.name, value:e.id}
                            }).filter(function( data ) {
                                return data !== undefined;
                            })}
                        />
                    </div>
                    <div className="form-group col-sm-12 p-0" style={this.state.tags.length ? {display:'block'} : {display:'none'}}>
                        <label className="col-sm-12 pl-0">(Optional) Apply A Tag:</label>
                        <Select
                            className="col-sm-12 p-0"
                            value={this.state.activeTags}
                            onChange={this.selectActiveLists.bind(this,'activeTags')}
                            options= { this.state.tags.map((e, key) => {
                                return { label:e.tag, value:e.id}
                            })}
                        />
                    </div>
                    <div className="form-group row" style={this.state.activeList ? {display:'none'} : {display:'block'}}>
                        <div className="col-sm-12">
                            <button type="button" className="btn btn-primary" onClick={this.getActiveCampaign.bind(this)} disabled={this.state.activeList ? 'disabled' : ''}>
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
export default ActiveCampaign;
