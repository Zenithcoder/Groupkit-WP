import React, { Component } from 'react';
import {API} from '../Api'
import Select from 'react-select';
class Kartra extends Component{
    constructor(props) {
        super(props);
        this.state={
            oneTime:false,
            selectGroupId:'',
            activeUserId:'',
            AutoResponderError:'',
            AutoResponderSuccess:'',
            kartra:{
                app_id:'YpEJORItQUab',
                api_key:'',
                password:''
            },
            lists:[],
            activeList:''
        }
    }
    componentDidMount(){
        var response=this.props.setActiveAutoresponder('Kartra')        
        if(response.api_key && response.activeList){
            this.setState({
                kartra:{
                    app_id:response.app_id,
                    api_key:response.api_key,
                    password:response.password
                },
                activeList:response.activeList,
                selectGroupId:response.group_id,
                activeUserId:response.user_id
            })
            setTimeout(function(){
                this.getKartra()
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
            responder_type:'Kartra',
            responder_json:{
                activeList:this.state.activeList,
                api_key:this.state.kartra.api_key,
                password:this.state.kartra.password,
                app_id:this.state.kartra.app_id
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
    /* mailerlite*/
    handleChang(param,event){
        this.state.kartra[param]=event.target.value
        this.setState({kartra:this.state.kartra});
    }
    selectActiveLists(param,event){
        this.setState({[param]:event});
    }
    
    getKartra(){
        API.kartra(this.state.kartra).then((res) => {
            if(res.data.code==200){
                if (!res.data.data.list.length) {
                    return this.errorMessage('Please create at least one subscription list in the integration');
                }

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
                    <label className="col-sm-12 col-form-label pl-0">Enter Your Kartra API Key & API Password</label>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input type="text" className="form-control"  placeholder="Your Kartra API Key" value={this.state.kartra.api_key} onChange={this.handleChang.bind(this,"api_key")} />
                        </div>
                    </div>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input type="text" className="form-control"  placeholder="Your Kartra API Password" value={this.state.kartra.password} onChange={this.handleChang.bind(this,"password")} />
                        </div>
                    </div>
                    <div className="form-group col-sm-12 p-0" style={this.state.lists.length ? {display:'block'} : {display:'none'}}>
                        <label className="col-sm-12 pl-0">Select Your List</label>
                        <Select
                            className="col-sm-12 p-0"
                            value={this.state.activeList} 
                            onChange={this.selectActiveLists.bind(this,'activeList')}
                            options= { this.state.lists.map((e, key) => {
                                return { label:e, value:e}
                            })}
                        />
                    </div>
                    <div className="form-group row" style={this.state.activeList ? {display:'none'} : {display:'block'}}>
                        <div className="col-sm-12">
                            <button type="button" className="btn btn-primary" onClick={this.getKartra.bind(this)} disabled={this.state.activeList ? 'disabled' : ''}>
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
export default Kartra;