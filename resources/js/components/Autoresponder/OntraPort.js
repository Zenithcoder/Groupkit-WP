import React, {Component} from 'react';
import {API} from '../Api';

/**
 * The API end-point for maintaining the OntraPort integration process.
 */
class OntraPort extends Component {
    constructor(props) {
        super(props);
        this.state = {
            oneTime: false,
            selectGroupId: '',
            activeUserId: '',
            AutoResponderError: '',
            AutoResponderSuccess: '',
            ontraPort: {
                app_id: '',
                app_key: '',
            },
            disabled: false,
        }
    }

    /**
     * Invoked once on the client immediately after the initial rendering occurs.
     */
    componentDidMount() {
        this.setActiveAutoresponder();
    }

    /**
     * sets values like app_id,app_key to be mount on component
     */
    setActiveAutoresponder() {
        let response = this.props.setActiveAutoresponder('OntraPort');
        if (response.app_key && response.app_id) {
            return this.setState({
                ontraPort: {
                    app_id: response.app_id,
                    app_key: response.app_key,
                },
                selectGroupId: response.group_id,
                activeUserId: response.user_id,
            });
        }

        this.setState({
            selectGroupId: response.group_id,
            activeUserId: response.user_id,
        });
    }

    /**
     * Shows provided success message on the screen
     *
     * @param {string} message that will be displayed
     */
    successMessage(message) {
        this.setState({ AutoResponderSuccess: message });

        this.hideMessage();
    }

    /**
     * Shows provided error message on the screen
     *
     * @param {string} message that will be displayed
     */
    errorMessage(message) {
        this.setState({ AutoResponderError: message });

        this.hideMessage();
    }

    /**
     * Hides provided message on the screen after passed milliseconds.
     *
     * @param {int} milliSeconds that will be hide message after given time interval
     */
    hideMessage(milliSeconds = 3000) {
        setTimeout(() => {
            this.setState({AutoResponderSuccess: '', AutoResponderError: ''});
        }, milliSeconds);
    }
    /**
     * Store passed users details like app_id,app_key in auto_responder table
     */
    saveAutoresponder() {
        let object = {
            responder_type: 'OntraPort',
            responder_json: {
                app_id: this.state.ontraPort.app_id,
                app_key: this.state.ontraPort.app_key,
            },
            group_id: this.state.selectGroupId,
            user_id: this.state.activeUserId,
            is_check: 0,
        };

        API.save_autoresponder(object)
            .then(
                (response) => {
                    this.successMessage(response.data.message);
                    this.props.getGroups();
                    this.props.showRemoveAutoresponder();
                }
            ).catch(
                (error) => {
                    this.setState({disabled: false}); //enabling "Save Integration" button

                    let errorMessage = (error.response)
                        ? error.response.data.message
                        : "We were unable to reach OntraPort.  Please try again later.";

                    this.errorMessage(errorMessage);
                }
            )
        ;
    }

    /**
     * Binds users ontraPort details like app_id,app_key on change event
     *
     * @param param  contains app_id,app_key
     * @param event contains object
     */
    handleChange(param, event) {
        this.state.ontraPort[param] = event.target.value;
        this.setState({ontraPort: this.state.ontraPort});
    }

    /**
     * Calls API to validate passed users details like app_id,app_key
     */
    getOntraPort() {
        //validating request to call again until response is returned.
        if (this.state.disabled) {
            return;
        }
        this.setState({disabled: true}); // disabling "Save Integration" button until the request is completed

        API.ontraPort(this.state.ontraPort)
            .then(
                (response) => {
                    let errorMessage = (response.response) ? response.response.data.message : null;

                    if (errorMessage) {
                        this.errorMessage(errorMessage);
                    } else {
                        this.saveAutoresponder();
                    }
                }
            ).catch(
                (error) => {
                    this.setState({disabled: false}); //enabling "Save Integration" button

                    let errorMessage = (error.response)
                        ? error.response.data.message
                        : "We were unable to reach OntraPort.  Please try again later.";

                    this.errorMessage(errorMessage);
                }
            ).then(
                () => {
                    // The request is complete, so, FINALLY enable "Save Integration" button
                    this.setState({disabled: false});
                }
            )
        ;
    }

    /**
     * renders Html contents via components to integrate OntraPort.
     */
    render() {
        return (
            <div>
                <form className="forms-sample" id="autoresponder_from">
                    <label className="col-sm-12 col-form-label pl-0">Enter Your OntraPort App Id & App Key</label>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="OntraPort App Id"
                                value={this.state.ontraPort.app_id}
                                onChange={this.handleChange.bind(this, 'app_id')}
                            />
                        </div>
                    </div>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="OntraPort App Key"
                                value={this.state.ontraPort.app_key}
                                onChange={this.handleChange.bind(this, 'app_key')}
                            />
                        </div>
                    </div>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={this.getOntraPort.bind(this)}
                                disabled={this.state.disabled ? 'disabled' : ''}
                            >
                                {this.state.disabled ? 'Checking...' : 'Save Integration'}
                            </button>
                        </div>
                    </div>
                    <div
                        className="alert alert-fill-danger"
                        role="alert"
                        style={{display: this.state.AutoResponderError ? 'block' : 'none'}}
                    >
                        <span>{this.state.AutoResponderError}</span>
                    </div>
                    <div
                        className="alert alert-fill-success"
                        role="alert"
                        style={{display: this.state.AutoResponderSuccess ? 'block' : 'none'}}
                    >
                        <span>{this.state.AutoResponderSuccess}</span>
                    </div>
                </form>
            </div>
        );
    }
}

export default OntraPort;
