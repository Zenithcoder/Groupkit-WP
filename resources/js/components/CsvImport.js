import React, { Component } from 'react';
import Select from 'react-select';
import './css/importcsv.css';
import swal from 'sweetalert';
import { API } from './Api';
import { readString } from 'react-papaparse';
import Modal from 'react-bootstrap/Modal';

/**
 * Used from the cloud app home screen upon clicking the [CSV Import] button
 */
class CsvImport extends Component{
    constructor(props) {
        super(props);
        this.state={
            scrapingLoader:0,
            isFormVisible:false,
            responseError:'',
            aboutUrl:'',
            group_data:{},
            csv_data:{},
            fileName: '',
            currentStep:1,
            columns:{},
            Skipline:1,
            LineNumber:1,
            columnNameMappings:{
                'date_add':'',
                'f_name':'',
                'l_name':'',
                'email':'',
                'userid':'',
                'q1_answer':'',
                'q2_answer':'',
                'q3_answer':'',
                'notes':'',
                'lives_in': '',
                'agreed_group_rules': '',
            }
        }
    }
    componentDidMount(){}
    componentWillReceiveProps(props) {}
    handleChange(event){
        this.setState({aboutUrl:event.target.value});
    };
    async importCsvSetup(e){
        var group_data={};
        try{
            var fb_basic_url=this.state.aboutUrl
            fb_basic_url = fb_basic_url.trim().toLowerCase();
            if(fb_basic_url.indexOf("facebook.com")> -1 && fb_basic_url.indexOf("/about") > -1){
                if(fb_basic_url.match(/\/groups\/([\w.]*)\//) != null
                && fb_basic_url.match(/\/groups\/([\w.]*)\//)[1] != null
                && fb_basic_url.match(/\/groups\/([\w.]*)\//)[1] != ""
                && fb_basic_url.match(/\/groups\/([\w.]*)\//)[1].trim() != ""){
                    fb_basic_url = fb_basic_url.match(/\/groups\/([\w.]*)\//)[1];
                    fb_basic_url = fb_basic_url.toLowerCase();
                }else{
                    fb_basic_url = "";
                }
            }else{
                fb_basic_url = "";
            }
        }catch(e){}
        if(fb_basic_url != null && fb_basic_url != "" && fb_basic_url.trim() != ""){
            var data=await this.getFbGroupDetails(fb_basic_url)
            if(data !=undefined &&
                data.groupid !=undefined &&
                data.groupid !=null &&
                data.groupname !=undefined &&
                data.groupname !=null
                ){
                    group_data.name = data.groupname;
                    group_data.id = data.groupid;
                    this.setState({currentStep:2})
                    this.setState({group_data:group_data})
            }else{
                swal({
                    text:`You have entered an invalid URL. Please try again!`,
                    buttons: true,
                });
            }
        }else{
            swal({
                text:`You have entered an invalid URL. Please try again!`,
                buttons: true,
            });
        }
    }
    getFbGroupDetails(fb_basic_url){
        return new Promise(function(resolve) {
            var data = { type: "getFbGroupDetails",'facebookUrlIdentifier':fb_basic_url};
            window.postMessage(data, "*");

            window.addEventListener("message", function(event) {
                if(event.data !=undefined && event.data.type=="sendFbGroupDetails" && event.data !=undefined){
                    resolve(event.data)
                }
            });
        });
    }
    nextUploadCsv(){
        this.setState({currentStep:3})
    }
    nextImportCsv(){
        if(this.state.csv_data.data.length > 0 && this.state.csv_data.data !=undefined){
            var csvData=this.state.csv_data.data
            var skiplines = this.state.Skipline;
            var skipnumber = this.state.LineNumber;
            var import_array_data = [];
            if(skiplines==1){
                for (var j = 1; j < parseInt(csvData.length); j++) {
                    import_array_data.push(csvData[j]);
                }
            }
            if(skiplines==2){
                if(parseInt(csvData.length) <= parseInt(skipnumber)){
                    swal({
                        text:`Skip range is bigger or equal to the number of lines.`,
                        buttons: true,
                    });
                    return
                }
                for(var i = parseInt(skipnumber), l = parseInt(csvData.length); i<l; i++){
                    import_array_data.push(csvData[i]);
                }
            }
            if(skiplines==3){
                import_array_data=csvData;
            }
            if(import_array_data[0].length !=undefined && import_array_data[0].length){
                var csvFile=this.state.csv_data
                csvFile['data']=import_array_data
                this.setState({csv_data:csvFile})
                this.fill_preview_columns(parseInt(import_array_data[0].length));
                this.setState({currentStep:5})
            }else{
                swal({
                    text:`There was an error while parsing collumns.`,
                    buttons: true,
                });
            }
        }
    }
    importFile(event){
        var self=this
        if (event.target.files && event.target.files[0]){
            var myFile = event.target.files[0];
            if (myFile.name != null
            && myFile.name.slice(-4).toLowerCase() == ".csv"
            && myFile.size != null
            && parseInt(myFile.size) > 0){
                try{
                var reader = new FileReader();
                reader.readAsText(myFile);
                this.setState({ fileName: myFile.name });
                reader.addEventListener('load', function(e){
                    self.UploadCsv(e.target.result)
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
    UploadCsv(data){
        var results = readString(data)
        this.setState({csv_data:results})
        this.setState({currentStep:4})
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
        var self = this;
        try{
            if(
                e != null
                && e.dataTransfer != null
                && e.dataTransfer.files != null
                && e.dataTransfer.files[0] != null
                && e.dataTransfer.files[0].name != null
                && e.dataTransfer.files[0].name.substr(-4).toLowerCase() == ".csv"
                && e.dataTransfer.files[0].size != null
                && parseInt(e.dataTransfer.files[0].size) > 0){
                    var myFile = e.dataTransfer.files[0];
                    this.setState({ fileName: myFile.name });
                    var reader = new FileReader();
                    reader.readAsText(myFile);
                    reader.addEventListener("load", function(e){
                        self.UploadCsv(e.target.result)
                    });
            }else{
                fail = true;
            }
        }catch(e){
            fail = true;
        }
    }
    Skipline(e){
        this.setState({Skipline:e.target.value})
    }
    handleLineNumber(e){
        this.setState({LineNumber:e.target.value})
    }

    /**
     * Draws the CSV import form
     *
     * @returns {JSX.Element} The CSV import form at screen representing the current step
     */
    renderCsvImportForm(){
        if(this.state.currentStep==1){
            return(
                <Modal
                dialogClassName="modal-90w"
                show={this.state.isFormVisible}
                onHide={this.resetFormToFirstStep.bind(this,false)}
                backdrop="static"
                keyboard={false}
                >
                    <Modal.Header closeButton>
                        <Modal.Title>Enter Your <b>Group’s About Page URL</b> Below</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <div className="alert alert-fill-danger" role="alert" style={this.state.responseError ? {display:'block'} : {display:'none'}}>
                            <i className="fa fa-exclamation-triangle"></i>
                            {this.state.responseError}
                        </div>
                        <div className="form-group">
                            <img src="asset/images/csvimportimg.jpg" className="img-responsive" id="your_group_name_el" />
                        </div>
                        <div className="form-group">
                            <input type="text" className="form-control" value={this.state.aboutUrl} onChange={this.handleChange.bind(this) } placeholder="https://www.facebook.com/groups/name/about/" />
                        </div>
                    </Modal.Body>
                    <Modal.Footer className="footer-center">
                        <button type="button" className="btn btn-primary" onClick={this.importCsvSetup.bind(this)} disabled={this.state.scrapingLoader ? 'disabled' : '' }>
                            NEXT STEP
                            <i className="fa fa-spinner fa-spin fa-3x fa-fw ml-2 loader_spinner" style={ this.state.scrapingLoader ? {display:'block'} : {display:'none'}} ></i>
                        </button>
                    </Modal.Footer>
                </Modal>
            )
        }
        if(this.state.currentStep==2){
            return(
                <Modal
                dialogClassName="modal-90w"
                show={this.state.isFormVisible}
                onHide={this.resetFormToFirstStep.bind(this,false)}
                backdrop="static"
                keyboard={false}
                >
                    <Modal.Header closeButton>
                        <Modal.Title>Re-Confirm Your Group’s Data</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <br></br>
                        <div className="form-group">
                            <label>Group Name : <b>{this.state.group_data.name}</b></label>
                        </div>
                        <div className="form-group">
                            <label>Group ID : <b>{this.state.group_data.id}</b></label>
                        </div>
                    </Modal.Body>
                    <Modal.Footer className="footer-center">
                        <button type="button" className="btn btn-primary" onClick={this.nextUploadCsv.bind(this)} disabled={this.state.scrapingLoader ? 'disabled' : '' }>
                            NEXT STEP
                            <i className="fa fa-spinner fa-spin fa-3x fa-fw ml-2 loader_spinner" style={ this.state.scrapingLoader ? {display:'block'} : {display:'none'}} ></i>
                        </button>
                    </Modal.Footer>
                </Modal>
            )
        }
        if(this.state.currentStep==3){
            return(
                <Modal
                dialogClassName="modal-90w"
                show={this.state.isFormVisible}
                onHide={this.resetFormToFirstStep.bind(this,false)}
                backdrop="static"
                keyboard={false}
                >
                    <Modal.Header closeButton>
                        <Modal.Title>Upload Your Group Member’s CSV File</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <form id="gkit_upload_form" className="box has-advanced-upload" action="#"
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
                                    <button type="button" id="gkit_import_drag" className="btn btn-primary">CHOSE A FILE</button>
                                    <input className="csvButton" type="file" id="import_file" accept=".csv" onChange={this.importFile.bind(this)}/>
                                </div>
                                <br/>
                                <label><span className="box__dragndrop">Or drag it here ...</span></label>
                            </div>
                        </form>
                        <label>
                            <b>WARNING:</b> If you import the file you will overwrite existing group member's data.
                            We only recommend using this feature on new groups you have not yet connected to GroupKit.
                        </label>
                    </Modal.Body>
                </Modal>
            )
        }
        if(this.state.currentStep==4){
            const groupMemberCount = this.state.csv_data.data.length;
            return(
                <Modal
                dialogClassName="modal-90w"
                show={this.state.isFormVisible}
                onHide={this.resetFormToFirstStep.bind(this,false)}
                backdrop="static"
                keyboard={false}
                >
                    <Modal.Header closeButton>
                        <Modal.Title>{groupMemberCount} entr{ (groupMemberCount ==1) ? 'y' : 'ies'} were found in the file.</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <div className="row m-2">
                            <div className="form-check col-12">
                                <label className="form-check-label">
                                <input type="radio" className="form-check-input skip-line" name="optionsRadios" value="1" checked={this.state.Skipline==1 ? 'checked' : ''} onChange={this.Skipline.bind(this)}/>
                                Skip First Line (e.g. skip headings)
                                <i className="input-helper"></i></label>
                            </div>
                            <div className="form-check col-12">
                                <label className="form-check-label">
                                <input type="radio" className="form-check-input skip-line" name="optionsRadios" value="2" checked={this.state.Skipline==2 ? 'checked' : ''} onChange={this.Skipline.bind(this)}/>
                                Skip # Of Lines
                                <i className="input-helper"></i></label>
                                <input type="number" className="form-control inputLength" value={this.state.LineNumber} onChange={this.handleLineNumber.bind(this)} min="1" max="1000"></input>
                            </div>
                            <div className="form-check col-12">
                                <label className="form-check-label">
                                <input type="radio" className="form-check-input skip-line" name="optionsRadios" value="3" checked={this.state.Skipline==3 ? 'checked' : ''} onChange={this.Skipline.bind(this)}/>
                                Do Not Skip Any Lines
                                <i className="input-helper"></i></label>
                            </div>
                        </div>
                    </Modal.Body>
                    <Modal.Footer className="footer-center">
                        <button type="button" className="btn btn-primary" onClick={this.nextImportCsv.bind(this)} disabled={this.state.scrapingLoader ? 'disabled' : '' }>
                            NEXT STEP
                            <i className="fa fa-spinner fa-spin fa-3x fa-fw ml-2 loader_spinner" style={ this.state.scrapingLoader ? {display:'block'} : {display:'none'}} ></i>
                        </button>
                    </Modal.Footer>
                </Modal>
            )
        }
        if(this.state.currentStep==5){
            return(
                <Modal
                dialogClassName="modal-90w"
                show={this.state.isFormVisible}
                onHide={this.resetFormToFirstStep.bind(this,false)}
                backdrop="static"
                keyboard={false}
                >
                    <Modal.Header closeButton>
                        <Modal.Title>Map Your Group Member’s CSV Columns.</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <div className="card-body">
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">DATE ADDED</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'date_add')}
                                        options={this.state.columns.date_add}
                                        id="date_add_csv_imp"
                                    />
                                    <small className="">Column must be valid date string or timestamp value, or it will fallback to current timestamp.</small>
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="date_add"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">FIRST NAME</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'f_name')}
                                        options={this.state.columns.f_name}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="f_name"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">LAST NAME</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'l_name')}
                                        options={this.state.columns.l_name}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="l_name"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">EMAIL ADDRESS</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'email')}
                                        options={this.state.columns.email}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="email"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">USER ID</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'userid')}
                                        options={this.state.columns.userid}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="userid"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">Q1 ANSWER</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'q1_answer')}
                                        options={this.state.columns.q1_answer}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="q1_answer"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">Q2 ANSWER</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'q2_answer')}
                                        options={this.state.columns.q2_answer}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="q2_answer"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">Q3 ANSWER</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'q3_answer')}
                                        options={this.state.columns.q3_answer}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="q3_answer"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">NOTES</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'notes')}
                                        options={this.state.columns.notes}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="notes"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">TAGS</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this,'tags')}
                                        options={this.state.columns.tags}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="tags"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">LIVES IN</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this, 'lives_in')}
                                        options={this.state.columns.lives_in}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="lives_in"></span>
                                </div>
                            </div>
                            <div className="form-group row">
                                <label className="col-sm-3 col-form-label">AGREED TO GROUP RULES</label>
                                <div className="col-sm-9">
                                    <Select
                                        className="col-12 p-0"
                                        onChange={this.mapHeadingToColumn.bind(this, 'agreed_group_rules')}
                                        options={this.state.columns.agreed_group_rules}
                                    />
                                    <small className="col-12 row label_preview"><b>Preview:</b></small>
                                    <span className="col-12 data_preview" id="agreed_group_rules"></span>
                                </div>
                            </div>
                        </div>
                    </Modal.Body>
                    <Modal.Footer className="footer-center">
                        <button type="button" className="btn btn-primary" onClick={this.insertCsvData.bind(this)} disabled={this.state.scrapingLoader ? 'disabled' : '' }>
                            Import
                            <i className="fa fa-spinner fa-spin fa-3x fa-fw ml-2 loader_spinner" style={ this.state.scrapingLoader ? {display:'block'} : {display:'none'}} ></i>
                        </button>
                    </Modal.Footer>
                </Modal>
            )
        }
    }

    /**
     * Maps an expected named heading to a column
     *
     * @param {string} headingName - The heading name key to be associated with a column
     * @param {Event} event - The onchange Event which triggered this function
     */
    mapHeadingToColumn(headingName, event){
        var csv_import_object = this.state.csv_data.data
        var rand = this.rendomKey(csv_import_object.length);
        var value = event.value
        //
        var currentMappings=this.state.columnNameMappings
        currentMappings[headingName]=value
        this.setState({columnNameMappings:currentMappings})
        //
        var first = false;
        var second = false;
        if(value.indexOf("col_fp") > -1){
            first = true;
        }
        if(value.indexOf("col_sp") > -1){
            second = true;
        }
        if(second===true || first===true){
            value = value.toLowerCase().replace("col_fp_","");
            value = value.toLowerCase().replace("col_sp_","");
            var int = parseInt(value);
            var temp_arr = csv_import_object[rand][int] !=undefined ? csv_import_object[rand][int].trim().split(" ") : [];
            if(first == true){
                if(temp_arr != null && temp_arr[0] != null && temp_arr[0] != "" && temp_arr[0].trim() != ""){
                    document.querySelector('#'+headingName).innerHTML=temp_arr[0];
                }else{
                    document.querySelector('#'+headingName).innerHTML=''
                }
            }
            if(second == true){
                if(temp_arr != null && temp_arr[1] != null && temp_arr[1] != "" && temp_arr[1].trim() != ""){
                    document.querySelector('#'+headingName).innerHTML=temp_arr[1];
                }else{
                    document.querySelector('#'+headingName).innerHTML=''
                }
            }
        }else{
            value = value.toLowerCase().replace("col_","");
            var int = parseInt(value);
            if(int > -1){
                if(csv_import_object[rand][int] != null
                && csv_import_object[rand][int] != ""
                && csv_import_object[rand][int].trim() != ""){
                    document.querySelector('#'+headingName).innerHTML=csv_import_object[rand][int].trim()
                }
            }
        }
    }

    rendomKey(e){
        try{
            var t = new Uint32Array(1)
            window.crypto.getRandomValues(t);
            var random  = t[0] / 4294967296;
            var returnint =  parseInt(Math.floor(Math.abs(e * random)));
            if(returnint<e || returnint>=0){
            }else{
                returnint = 0;
            }
            return returnint;
        }catch(e){
            return 0;
        }
    }

    number_to_column_name(number){
        try{
            if(number != null && parseInt(number) > 0
            && parseInt(number) < 703){
                number = parseInt(number);
            }else{
                return "A";
            }
            var columnnames = ["A","A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z"];
            var a = number % 26; // remaining
            if(a <1){
                a = 26;
            }
            var b = parseInt(Math.floor((number-1)/26)); // first column x num of
            if(b < 1){
                return columnnames[a];
            }else{
                return columnnames[b] + columnnames[a];
            }
        }catch(e){
            return "A";
        }
    }
    fill_preview_columns(columnlength){
        if(columnlength == null || parseInt(columnlength)<= 0){
            return false;
        }
        let columns = {};
        Object.keys(this.state.columnNameMappings).forEach((column) => {
            columns[column] = [];
        });

        for (let i = 0, l = parseInt(columnlength); i < l; i++) {
            const value = "col_" + parseInt(i);
            const label = 'Col ' + this.number_to_column_name(parseInt(i + 1));
            const obj = {'label': label, 'value': value}

            Object.keys(columns).forEach((column) => {
                columns[column].push(obj);
            });
        }

        for (let i = 0, l = parseInt(columnlength); i < l; i++) {
            const fvalue = "col_fp_" + parseInt(i);
            const flabel = 'Col ' + this.number_to_column_name(parseInt(i + 1)) + 'split [space] first part';
            const fobj = {'label': flabel, 'value': fvalue}

            const svalue = "col_sp_" + parseInt(i);
            const slabel = 'Col ' + this.number_to_column_name(parseInt(i + 1)) + 'split [space] second par';
            const sobj = {'label': slabel, 'value': svalue}

            columns['f_name'].push(fobj)
            columns['f_name'].push(sobj)
            columns['l_name'].push(fobj)
            columns['l_name'].push(sobj)

        }
        this.setState({columns})
    }

    /**
     * Resets the CSV Import form to the first step in the sequence of states
     *
     * @param {boolean} shouldFormBeDisplayed - if true, the form will be displayed, otherwise it is reset without displaying
     * @returns {Promise<void>} Promise of resetting the CSV Import form
     */
    async resetFormToFirstStep(shouldFormBeDisplayed){
        if(shouldFormBeDisplayed){
            var response=await this.props.isExtensionInstalled();
            if(response===false){
                return;
            }
        }
        this.setState({scrapingLoader:0});
        this.setState({isFormVisible:shouldFormBeDisplayed});
        this.setState({currentStep:1});
    }

    insertCsvData(){
        var csv_import_object ={"name":this.state.group_data.name,"id":this.state.group_data.id,"import_data":this.state.csv_data.data}
        if(csv_import_object != null
            && csv_import_object.name != ""
            && csv_import_object.name.trim() != ""
            && csv_import_object.id != null
            && parseInt(csv_import_object.id) > 0
            && csv_import_object.import_data != null
            && csv_import_object.import_data.length != null
            && parseInt(csv_import_object.import_data.length) > 0
            && Array.isArray(csv_import_object.import_data)
            ){
                let time_colum = -1;
                let fn_column = -1;
                let ln_column = -1;
                let fn_column_first = false;
                let ln_column_first = false;
                let fn_column_second = false;
                let ln_column_second = false;
                let email_column = -1;
                let user_id_column = -1;
                let q1a_column = -1;
                let q2a_column = -1;
                let q3a_column = -1;
                let notes_column = -1;
                let tags_column = -1;
                let lives_in_column;
                let agreed_group_rules_column = -1;

                let time_colum_str = "";
                let fn_column_str = "";
                let ln_column_str = "";
                let email_column_str = "";
                let user_id_column_str = "";
                let q1a_column_str = "";
                let q2a_column_str = "";
                let q3a_column_str = "";
                let tags_column_str = "";
                let agreed_group_rules_column_str = "";

                try{
                    time_colum_str =  this.state.columnNameMappings.date_add
                    if(time_colum_str.toLowerCase().trim() != "def"){
                    time_colum_str = time_colum_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(time_colum_str))){
                    time_colum = parseInt(time_colum_str);
                    }
                    }
                }catch(e){}
                try{
                    fn_column_str = this.state.columnNameMappings.f_name
                    if(fn_column_str.toLowerCase().trim() != "def"){
                    if(fn_column_str.indexOf("col_fp") > -1){
                        fn_column_first = true;
                    }
                    if(fn_column_str.indexOf("col_sp") > -1){
                        fn_column_second = true;
                    }
                    fn_column_str = fn_column_str.toLowerCase().replace("col_fp_","");
                    fn_column_str = fn_column_str.toLowerCase().replace("col_sp_","");
                    fn_column_str = fn_column_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(fn_column_str))){
                    fn_column = parseInt(fn_column_str);
                    }
                    }
                }catch(e){}
                try{
                    ln_column_str = this.state.columnNameMappings.l_name
                    if(ln_column_str.toLowerCase().trim() != "def"){
                    if(ln_column_str.indexOf("col_fp") > -1){
                        ln_column_first = true;
                    }
                    if(ln_column_str.indexOf("col_sp") > -1){
                        ln_column_second = true;
                    }
                    ln_column_str = ln_column_str.toLowerCase().replace("col_fp_","");
                    ln_column_str = ln_column_str.toLowerCase().replace("col_sp_","");
                    ln_column_str = ln_column_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(ln_column_str))){
                    ln_column = parseInt(ln_column_str);
                    }
                    }
                }catch(e){}
                try{
                    email_column_str =  this.state.columnNameMappings.email
                    if(email_column_str.toLowerCase().trim() != "def"){
                    email_column_str = email_column_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(email_column_str))){
                    email_column = parseInt(email_column_str);
                    }
                    }
                }catch(e){}
                try{

                    user_id_column_str =  this.state.columnNameMappings.userid
                    if(user_id_column_str.toLowerCase().trim() != "def"){
                    user_id_column_str = user_id_column_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(user_id_column_str))){
                    user_id_column = parseInt(user_id_column_str);
                    }
                    }
                }catch(e){}
                try{

                    q1a_column_str =  this.state.columnNameMappings.q1_answer
                    if(q1a_column_str.toLowerCase().trim() != "def"){
                    q1a_column_str = q1a_column_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(q1a_column_str))){
                    q1a_column = parseInt(q1a_column_str);
                    }
                    }
                }catch(e){}
                try{
                    q2a_column_str =  this.state.columnNameMappings.q2_answer
                    if(q2a_column_str.toLowerCase().trim() != "def"){
                    q2a_column_str = q2a_column_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(q2a_column_str))){
                    q2a_column = parseInt(q2a_column_str);
                    }
                    }
                }catch(e){}
                try{
                    q3a_column_str =  this.state.columnNameMappings.q3_answer
                    if(q3a_column_str.toLowerCase().trim() != "def"){
                    q3a_column_str = q3a_column_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(q3a_column_str))){
                    q3a_column = parseInt(q3a_column_str);
                    }
                    }
                }catch(e){}
                try{
                    notes_column_str =  this.state.columnNameMappings.notes
                    if(notes_column_str.toLowerCase().trim() != "def"){
                    notes_column_str = notes_column_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(notes_column_str))){
                    notes_column = parseInt(notes_column_str);
                    }
                    }
                }catch(e){}

                try{
                    tags_column_str =  this.state.columnNameMappings.tags
                    if(tags_column_str.toLowerCase().trim() != "def"){
                    tags_column_str = tags_column_str.toLowerCase().replace("col_","");
                    if(!isNaN(parseInt(tags_column_str))){
                    tags_column = parseInt(tags_column_str);
                    }
                    }
                }catch(e){}

                if (this.state.columnNameMappings.lives_in) {
                    lives_in_column = parseInt(this.state.columnNameMappings.lives_in.toLowerCase().replace("col_",""));
                }

                try{
                    agreed_group_rules_column_str = this.state.columnNameMappings.agreed_group_rules;
                    if(agreed_group_rules_column_str.toLowerCase().trim() != "def"){
                        agreed_group_rules_column_str = agreed_group_rules_column_str.toLowerCase().replace("col_","");
                        if (parseInt(agreed_group_rules_column_str)) {
                            agreed_group_rules_column = parseInt(agreed_group_rules_column_str);
                        }
                    }
                }catch(e){}

                var a_ob = {};
                var groupMembersData = [];
                var rowid = 0;
                var dateadded = 0;
                var userid = "0";
                var a1 = "";
                var a2 = "";
                var a3 = "";
                var fname = "";
                var lname = "";
                var email = "-";
                var notes = "";
                var tags = "";
                let livesIn = "";
                let agreedGroupRules = "";
                var responder = "No Email";

                for(var i = 0, l = parseInt(csv_import_object.import_data.length); i<l; i++){
                    rowid++;
                    dateadded = Math.round(new Date().getTime() / 1000);
                    userid = "0";
                    a1 = "";
                    a2 = "";
                    a3 = "";
                    fname = "";
                    lname = "";
                    email = "-";
                    notes = "";
                    tags = "";
                    livesIn = "";
                    agreedGroupRules = "";
                    responder = "No Email";
                    try{
                        if (time_colum != null
                            && parseInt(time_colum)> -1
                        ){
                            if(csv_import_object.import_data[i] != null
                                && csv_import_object.import_data[i][time_colum] != null
                                && csv_import_object.import_data[i][time_colum] != ""
                                && csv_import_object.import_data[i][time_colum].trim() != ""
                                && csv_import_object.import_data[i][time_colum].trim() != "-"){
                                dateadded = csv_import_object.import_data[i][time_colum];
                            }
                        }
                    }catch(e){}
                    try{
                    if (fn_column != null
                        && parseInt(fn_column)> -1
                    ){
                        if(csv_import_object.import_data[i] != null
                        && csv_import_object.import_data[i][fn_column] != null
                        && csv_import_object.import_data[i][fn_column] != ""
                        && csv_import_object.import_data[i][fn_column].trim() != ""
                        && csv_import_object.import_data[i][fn_column].trim() != "-"){
                            if(typeof fn_column_first != null && fn_column_first != null
                            && fn_column_first == true){
                                var temp_arr = csv_import_object.import_data[i][fn_column].trim().split(" ");
                                if(temp_arr != null && temp_arr[0] != null && temp_arr[0] != "" && temp_arr[0].trim() != ""){
                                    fname = temp_arr[0].trim();
                                }
                            }else if(typeof fn_column_second != null && fn_column_second != null
                            && fn_column_second == true){
                                var temp_arr = csv_import_object.import_data[i][fn_column].trim().split(" ");
                                if(temp_arr != null && temp_arr[1] != null && temp_arr[1] != "" && temp_arr[1].trim() != ""){
                                    fname = temp_arr[1].trim();
                                }
                            }else{
                                    fname = csv_import_object.import_data[i][fn_column].trim();
                            }
                        }
                    }
                    }catch(e){}

                    try{
                    if (ln_column != null
                        &&  parseInt(ln_column) > -1
                    ){
                        if(csv_import_object.import_data[i] != null
                            && csv_import_object.import_data[i][ln_column] != null
                            && csv_import_object.import_data[i][ln_column] != ""
                            && csv_import_object.import_data[i][ln_column].trim() != ""
                            && csv_import_object.import_data[i][ln_column].trim() != "-"){
                                if(typeof ln_column_first != null && ln_column_first != null
                                    && ln_column_first == true){
                                        var temp_arr = csv_import_object.import_data[i][ln_column].trim().split(" ");
                                        if(temp_arr != null && temp_arr[0] != null && temp_arr[0] != "" && temp_arr[0].trim() != ""){
                                            lname = temp_arr[0].trim();
                                        }
                                    }else if(typeof ln_column_second != null && ln_column_second != null
                                    && ln_column_second == true){
                                        var temp_arr = csv_import_object.import_data[i][ln_column].trim().split(" ");
                                        if(temp_arr != null && temp_arr[1] != null && temp_arr[1] != "" && temp_arr[1].trim() != ""){
                                            lname = temp_arr[1].trim();
                                        }
                                    }else{
                                        lname = csv_import_object.import_data[i][ln_column].trim();
                                    }
                        }
                    }
                    }catch(e){}
                    try{
                    if (email_column != null
                        && parseInt(email_column) > -1
                    ){
                        if(csv_import_object.import_data[i] != null
                            && csv_import_object.import_data[i][email_column] != null
                            && csv_import_object.import_data[i][email_column] != ""
                            && csv_import_object.import_data[i][email_column].trim() != ""
                            && csv_import_object.import_data[i][email_column].trim() != "-"){
                                email = csv_import_object.import_data[i][email_column].trim();
                                responder = "Not Added";
                        }
                    }
                    }catch(e){}

                    try{
                    if (user_id_column != null
                        &&  parseInt(user_id_column) > -1
                    ){
                        if(csv_import_object.import_data[i] != null
                            && csv_import_object.import_data[i][user_id_column] != null
                            && csv_import_object.import_data[i][user_id_column] != ""
                            && csv_import_object.import_data[i][user_id_column].trim() != ""
                            && csv_import_object.import_data[i][user_id_column].trim() != "-"){
                                userid = csv_import_object.import_data[i][user_id_column].trim();
                        }
                    }
                    }catch(e){}
                    try{
                    if (q1a_column != null
                        &&  parseInt(q1a_column) > -1
                    ){
                        if(csv_import_object.import_data[i] != null
                            && csv_import_object.import_data[i][q1a_column] != null
                            && csv_import_object.import_data[i][q1a_column] != ""
                            && csv_import_object.import_data[i][q1a_column].trim() != ""
                            && csv_import_object.import_data[i][q1a_column].trim() != "-"){
                                a1 = csv_import_object.import_data[i][q1a_column].trim();
                        }
                    }
                    }catch(e){}
                    try{
                    if (q2a_column != null
                        &&  parseInt(q2a_column) > -1
                    ){
                        if(csv_import_object.import_data[i] != null
                            && csv_import_object.import_data[i][q2a_column] != null
                            && csv_import_object.import_data[i][q2a_column] != ""
                            && csv_import_object.import_data[i][q2a_column].trim() != ""
                            && csv_import_object.import_data[i][q2a_column].trim() != "-"){
                                a2 = csv_import_object.import_data[i][q2a_column].trim();
                        }
                    }
                    }catch(e){}
                    try{
                    if (q3a_column != null
                        &&  parseInt(q3a_column) > -1
                    ){
                        if(csv_import_object.import_data[i] != null
                            && csv_import_object.import_data[i][q3a_column] != null
                            && csv_import_object.import_data[i][q3a_column] != ""
                            && csv_import_object.import_data[i][q3a_column].trim() != ""
                            && csv_import_object.import_data[i][q3a_column].trim() != "-"){
                                a3 = csv_import_object.import_data[i][q3a_column].trim();
                        }
                    }
                    }catch(e){}
                    try{
                    if (notes_column != null
                        &&  parseInt(notes_column) > -1
                    ){
                        if(csv_import_object.import_data[i] != null
                            && csv_import_object.import_data[i][notes_column] != null
                            && csv_import_object.import_data[i][notes_column] != ""
                            && csv_import_object.import_data[i][notes_column].trim() != ""
                            && csv_import_object.import_data[i][notes_column].trim() != "-"){
                                notes = csv_import_object.import_data[i][notes_column].trim();
                        }
                    }
                    }catch(e){}
                    try{
                    if (tags_column != null
                        &&  parseInt(tags_column) > -1
                    ){
                        if(csv_import_object.import_data[i] != null
                            && csv_import_object.import_data[i][tags_column] != null
                            && csv_import_object.import_data[i][tags_column] != ""
                            && csv_import_object.import_data[i][tags_column].trim() != ""
                            && csv_import_object.import_data[i][tags_column].trim() != "-"){
                                tags = csv_import_object.import_data[i][tags_column].trim();
                        }
                    }
                    }catch(e){}

                    if (lives_in_column) {
                        livesIn = csv_import_object.import_data[i][lives_in_column].trim()
                    }

                    try {
                        if (
                            agreed_group_rules_column
                            && parseInt(agreed_group_rules_column) > -1
                        ) {
                            const agreedGroupRulesColumn = csv_import_object.import_data[i][agreed_group_rules_column]
                            if (
                                csv_import_object.import_data[i]
                                && agreedGroupRulesColumn
                                && agreedGroupRulesColumn.trim()
                                && agreedGroupRulesColumn.trim() !== "-"
                            ) {
                                agreedGroupRules = agreedGroupRulesColumn.trim();
                            }
                        }
                    } catch (e) {}

                    a_ob = {};
                    a_ob.tags = tags;
                    a_ob.lives_in = livesIn;
                    a_ob.agreed_group_rules = agreedGroupRules;
                    a_ob.notes = notes;
                    a_ob.rowid = rowid;
                    a_ob.f_name = fname;
                    a_ob.l_name = lname;
                    a_ob.email = email;
                    a_ob.respond_status = responder;
                    a_ob.user_id = userid;
                    a_ob.date_add_time = dateadded;
                    a_ob.a1 = a1;
                    a_ob.a2 = a2;
                    a_ob.a3 = a3;
                    a_ob.fb_id = parseInt(csv_import_object.id).toString();
                    groupMembersData.push(a_ob);
                }
                var obj={
                    'facebook_groups':{
                        "fb_id":csv_import_object.id,
                        "fb_name":csv_import_object.name,
                        "img":""
                    },
                    group_members: groupMembersData,
                    file_name: this.state.fileName,
                }
                API.importCsvFile(obj)
                    .then((response) => {
                        const message = document.createElement('span');
                        message.innerHTML = response.data.message;
                        message.classList = '';

                        if (response.data.code === 200) {
                            this.setState({
                                scrapingLoader: 1,
                                isFormVisible: false,
                            })
                            this.props.getGroups();
                        }

                        swal({
                            content: message,
                            buttons: {
                                confirm: true,
                            },
                        });
                    })
                    .catch((error) => {
                        console.log(error)
                    });
            }
    }
    render() {
        return (
            <span style={{display:'flex'}}>
                <button className="btn btn-sm btn-primary mr-2" onClick={this.resetFormToFirstStep.bind(this,true)} style={{borderRadius:'unset'}}>
                    <i className="fa fa-cloud-upload"></i> CSV Import
                </button>
                {this.state.isFormVisible ? this.renderCsvImportForm() : ''}
            </span>
        );
    }
}
export default CsvImport;
