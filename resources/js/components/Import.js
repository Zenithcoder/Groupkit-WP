import React, { Component } from 'react';
import './css/importcsv.css';
import swal from 'sweetalert'
import {API} from './Api'
import Modal from 'react-bootstrap/Modal'
class Import extends Component{
    constructor(props) {
        super(props);
        this.state={
            scrapingLoader:0,
            importCsv:0,
            responseError:'',
            isLoading:0
        }        
    }
    componentDidMount(){}
    componentWillReceiveProps(props) {}
    handleChange(event){}; 
    uploadCsvData(data){
        this.setState({isLoading:1})
        try{
            var obj=JSON.parse(data);
        }catch(e){
            swal({
                text:`Invalid file type or empty file.`,
                buttons: true,
            });
            this.setState({isLoading:0})
            return
        }
        API.importFile(obj)
            .then((response) => {
                const message = document.createElement('span');
                message.innerHTML = response.data.message;
                message.classList = '';
                if (response.data.code === 200) {
                    this.setState({
                        scrapingLoader: 1,
                        importCsv: 0,
                    });
                    this.props.getGroups();
                }

                swal({
                    content: message,
                    buttons: {
                        confirm: true,
                    },
                });

                this.setState({ isLoading: 0 });
            })
            .catch((error) => {
                //console.log(error)
            });
    }
    importFile(event){
        var self=this
        if (event.target.files && event.target.files[0]){
            var myFile = event.target.files[0];
            if (myFile.name != null
            && (myFile.name.slice(-5).toLowerCase() == ".gkit"
            || myFile.name.slice(-4).toLowerCase() == ".txt"
            )
            && myFile.size != null
            && parseInt(myFile.size) > 0) {
                try{
                    var reader = new FileReader();
                    reader.readAsText(myFile);
                    reader.addEventListener('load', function(e){
                        self.uploadCsvData(e.target.result);
                    });
                }catch(e){
                    swal({
                        text:`Invalid file type or empty file.`,
                        buttons: true,
                    });
                }
            }else{
                swal({
                    text:`Invalid file type or empty file.`,
                    buttons: true,
                });
            }
        }        
    }    
    /** Import csv */
    importCsvForm(){
        return(
            <Modal
            dialogClassName="modal-90w"
            show={this.state.importCsv ? true : false}
            onHide={this.setimportCsvForm.bind(this,0)}
            backdrop="static"
            keyboard={false}
            >
                <Modal.Header closeButton>
                    <Modal.Title>Select Your GroupKit Export File</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <form id="gkit_upload_form" className={`box has-advanced-upload ${this.state.isLoading ? "opacity-50" : ""}`} action="#" 
                        onDrag={this.addDargOverclass.bind(this,0)}
                        onDragStart={this.addDargOverclass.bind(this,0)}
                        onDragOver={this.addDargOverclass.bind(this,0)}
                        onDragEnter={this.addDargOverclass.bind(this,0)}
                        onDragEnd={this.addDargOverclass.bind(this,1)}
                        onDragLeave={this.addDargOverclass.bind(this,1)}
                        onDrop={this.dropAndUpload.bind(this)}
                    >
                        <div className="box__input" style={{textAlign:'center'}}>
                            <i className="fa fa-dropbox fa-4" aria-hidden="true"></i>
                            <br/>
                            <div className="mt-1">
                                <button type="button" id="gkit_import_drag" className="btn btn-primary">CHOOSE A FILE</button>
                                <input className="csvButton" type="file" id="import_file" accept=".gkit,.txt" onChange={this.importFile.bind(this)}/>
                            </div>
                            <br/>
                            <label><span className="box__dragndrop">Or drag it here ...</span></label>
                        </div>                        
                    </form>
                    <label>
                        <b>WARNING:</b> If you import the file you will overwrite existing group member's data.
                        We only recommend using this feature on new groups you have not yet connected to GroupKit.
                    </label>
                    {this.state.isLoading ?
                    <div className="loader-demo-box isLoading">
                        <div className="bar-loader">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                    :''}
                </Modal.Body>
            </Modal>
        )     
    }
    addDargOverclass(parm,e){
        var element=document.querySelector("form#gkit_upload_form")
        if(parm==0){
            element.classList.add("is-dragover");
        }else{
            element.classList.remove("is-dragover");
        }
        e.preventDefault();
        return;
    }
    dropAndUpload(e){
        var element=document.querySelector("form#gkit_upload_form")
        element.classList.remove("is-dragover");
        e.preventDefault();
        var fail = false;
        var self=this
        try{
            if(
            e != null
            && e != null
            && e.dataTransfer != null
            && e.dataTransfer.files != null
            && e.dataTransfer.files[0] != null
            && e.dataTransfer.files[0].name != null
            && (e.dataTransfer.files[0].name.substr(-5).toLowerCase() == ".gkit"
            || e.dataTransfer.files[0].name.substr(-4).toLowerCase() == ".txt"
            )
            && e.dataTransfer.files[0].size != null
            && parseInt(e.dataTransfer.files[0].size) > 0){
                var myFile = e.dataTransfer.files[0];
                var reader = new FileReader();
                reader.readAsText(myFile);
                reader.addEventListener("load", function(e){
                    self.uploadCsvData(e.target.result);
                });
            }else{
                fail = true;
            }
        }catch(e){
            fail = true;
        }
        if(fail){
            swal({
                text:`Invalid file type or empty file.`,
                buttons: true,
            }); 
        }
    }
    setimportCsvForm(parm){
        this.setState({isLoading:0})
        this.setState({scrapingLoader:0})
        this.setState({importCsv:parm})
    }
    render() {
        return (
            <span style={{display:'flex'}}>
                <button className="btn btn-sm btn-primary mr-2" onClick={this.setimportCsvForm.bind(this,1)} style={{borderRadius:'unset'}}>
                    <i className="fa fa-cloud-upload"></i>Import
                </button>
                {this.state.importCsv ? this.importCsvForm() : ''}                
            </span>
        );
    }
}
export default Import;