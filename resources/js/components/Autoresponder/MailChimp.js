import React, { Component } from 'react';
import {API} from '../Api'
import Select from 'react-select';
class MailChimp extends Component{
    constructor(props) {
        super(props);
        this.state={
            oneTime:false,
            selectGroupId:'',
            activeUserId:'',
            AutoResponderError:'',
            AutoResponderSuccess:'',
            mailchimp:{
                api_key:''
            },
            lists:[],
            activeList:''
        }
    }
    componentDidMount(){
        var response=this.props.setActiveAutoresponder('MailChimp')
        if(response.api_key && response.activeList){
            this.setState({
                mailchimp:{
                    api_key:response.api_key
                },
                activeList:response.activeList,
                selectGroupId:response.group_id,
                activeUserId:response.user_id
            })
            setTimeout(function(){
                this.getMailchimp()
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
            responder_type:'MailChimp',
            responder_json:{
                activeList:this.state.activeList,
                api_key:this.state.mailchimp.api_key
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
    /* mailchimp */
    handleChang(param,event){
        this.state.mailchimp[param]=event.target.value
        this.setState({mailchimp:this.state.mailchimp});
    }
    selectActiveLists(param,event){
        this.setState({[param]:event});
    }
    setActiveList(){
        var value=this.state.activeList.value
        if(this.state.activeList.label==''){
            var list=this.state.lists.map((key)=>{                
                if(key.id==value){
                    return {label: key.name, value: key.id}
                }
            }).filter(function( data ) {return data !== undefined; })
            if(list.length){
                this.setState({activeList:list[0]})
            }
        }
    }
    getMailchimp(){
        var parseData={};
        var apikeydata=this.state.mailchimp.api_key
        var apikeyarray = apikeydata.trim().split("-");
        if (apikeyarray != null && apikeyarray[0] != null && apikeyarray[1] != null){
            parseData['api_key']=apikeyarray[0]
            parseData['host_name']=apikeyarray[1]
        }
        API.getMailchimp(parseData).then((res) => {
            if(res.data.code==200){
                this.setState({
                    lists:res.data.data.list,
                });
                this.setActiveList()     
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
                    <label className="col-sm-12 col-form-label pl-0">Enter Your MailChimp Secret Key</label>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input type="text" className="form-control"  placeholder="MailChimp Secret Key" value={this.state.mailchimp.api_key} onChange={this.handleChang.bind(this,"api_key")} />
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
                    <div className="form-group row" style={this.state.activeList ? {display:'none'} : {display:'block'}}>
                        <div className="col-sm-12">
                            <button type="button" className="btn btn-primary" onClick={this.getMailchimp.bind(this)} disabled={this.state.activeList ? 'disabled' : ''}>
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
export default MailChimp;