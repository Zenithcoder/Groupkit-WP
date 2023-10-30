import React, {Component} from 'react';
import {API} from '../Api';
import Select from 'react-select';

/**
 * That handles InfusionSoft verification & integration process with users client id, client secret, authorize code, active tag.
 */
class InfusionSoft extends Component {
    constructor(props) {
        super(props);
        this.state = {
            selectGroupId: '',
            activeUserId: '',
            autoResponderError: '',
            autoResponderSuccess: '',
            successSaveTokens: false,
            infusionSoft: {
                clientId: '',
                clientSecret: '',
                accessToken: '',
                refreshToken: '',
                authorizeCode: '',
                activeTags: [],
            },
            tags: [],
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
     * Sets client id and client secret for InfusionSoft integration
     */
    setActiveAutoresponder() {
        let response = this.props.setActiveAutoresponder('InfusionSoft');
        if (response.clientSecret && response.clientId) {
            return this.setState({
                infusionSoft: {
                    clientId: response.clientId,
                    clientSecret: response.clientSecret,
                    activeTags: response.activeTags ?? [],
                },
                selectGroupId: response.group_id,
                activeUserId: response.user_id,
            }, () => {
                this.loadTags();
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
        this.setState({ autoResponderSuccess: message });
        this.hideMessage();
    }

    /**
     * Shows provided error message on the screen
     *
     * @param {string} message that will be displayed
     */
    errorMessage(message) {
        this.setState({ autoResponderError: message });
        this.hideMessage();
    }

    /**
     * Hides provided message on the screen after passed milliseconds.
     *
     * @param {int} milliSeconds that will be hide message after given time interval
     */
    hideMessage(milliSeconds = 3000) {
        setTimeout(() => {
            this.setState({ autoResponderSuccess: '', autoResponderError: '' });
        }, milliSeconds);
    }

    /**
     * Requests access token from the InfusionSoft API with client id, client secret, authorize code.
     */
    requestAccessToken() {
        this.setState({ disabled: true }); // disabling 'Save Integration' button until the request is completed

        API.InfusionSoft(this.state.infusionSoft).then((response) => {
            let errorMessage = response.response ? response.response.data.message : null;
            if (errorMessage) {
                return this.errorMessage(errorMessage);
            }

            let InfusionSoftData = this.state.infusionSoft;
            if (response.data.accessToken) {
                InfusionSoftData['accessToken'] = response.data.accessToken ?? '';
                InfusionSoftData['refreshToken'] = response.data.refreshToken ?? '';

                this.setState({ infusionSoft: InfusionSoftData }, () => {
                    this.saveAutoresponder().then((res) => {
                        if (res) {
                            this.setState({ successSaveTokens: true });
                            this.loadTags();
                        }
                    });
                });
            }
        }).catch((error) => {
            this.setState({ disabled: false }); //enabling 'Save Integration' button

            let errorMessage = error.response
                ? error.response.data.message
                : 'We were unable to reach InfusionSoft.  Please try again later.';
            this.errorMessage(errorMessage);
        }).finally(() => {
            // The request is complete, so, FINALLY enable 'Save Integration' button
            this.setState({ disabled: false });
        });
    }

    /**
     * Sends client id, client secret, authorize code, access token, refresh token, active tag to the saveAutoresponder API
     */
    saveAutoresponder() {
        const requestData = {
            responder_type: 'InfusionSoft',
            responder_json: {
                clientId: this.state.infusionSoft.clientId,
                clientSecret: this.state.infusionSoft.clientSecret,
                authorizeCode: this.state.infusionSoft.authorizeCode,
                accessToken: this.state.infusionSoft.accessToken,
                refreshToken: this.state.infusionSoft.refreshToken,
                activeTags: this.state.infusionSoft.activeTags,
            },
            group_id: this.state.selectGroupId,
            user_id: this.state.activeUserId,
            is_check: 0,
        };

        return API.save_autoresponder(requestData).then((response) => {
            this.successMessage(response.data.message);
            this.props.getGroups();
            this.props.showRemoveAutoresponder();

            return true;
        }).catch((error) => {
            this.setState({ disabled: false }); //enabling 'Save Integration' button
            let errorMessage = error.response
                ? error.response.data.message
                : 'We were unable to reach InfusionSoft. Please try again later.';
            this.errorMessage(errorMessage);

            return false;
        });
    }

    /**
     * Loads available client tags from the InfusionSoft API
     */
    loadTags() {
        API.CurlCall(`/infusionSoft/getTags/${this.state.selectGroupId}`, [], true).then((response) => {

            response.data.tags.sort((prev, next) => {
                return prev.category?.id - next.category?.id;
            });

            this.setState({ tags: response.data.tags });
        }).catch((error) => {
            if (error.response) {
                this.errorMessage(error.response.data.message);
            }
        });
    }

    /**
     * Binds users InfusionSoft details like client id, client secret on change event
     *
     * @param param contains client id, client secret
     * @param event contains object for change event
     */
    handleChange(param, event) {
        this.state.infusionSoft[param] = event.target.value;
        this.setState({ infusionSoft: this.state.infusionSoft, successSaveTokens: false }, () => {
            this.removeTags();
        });
    }

    /**
     * Removes existing tags by changing the client id or client secret value
     */
    removeTags() {
        this.state.infusionSoft['activeTags'] = [];
        this.setState({ infusionSoft: this.state.infusionSoft, tags: [] });
    }

    /**
     * Binds users InfusionSoft details on change select input event
     *
     * @param param contains state name of changed field
     * @param event contains object for change event
     */
    handleSelectChange(param, event) {
        this.state.infusionSoft[param] = event;
        this.setState({ infusionSoft: this.state.infusionSoft });
    }

    /**
     * Calls API to validate passed users details like client id, client secret are valid or not
     */
    getInfusionSoft() {
        let self = this;
        //validating request to call again until response is returned.
        if (self.state.disabled) {
            return;
        }

        if (self.state.successSaveTokens) {
            this.saveAutoresponder();
            return;
        }

        let InfusionSoftData = self.state.infusionSoft;

        if (!InfusionSoftData.clientId || !InfusionSoftData.clientSecret) {
            return this.errorMessage('Required parameters are missing.');
        }

        const params = {
            response_type: 'code',
            client_id: self.state.infusionSoft.clientId,
            redirect_uri: API.url('infusionSoftAuth/callback'),
            scope: 'full',
        }

        const url = 'https://accounts.infusionsoft.com/app/oauth/authorize?' + new URLSearchParams(params).toString();
        const width = 760;
        const height = 800;
        const left = (screen.width / 2) - (width / 2);
        const top = (screen.height / 2) - (height / 2);

        window.open(url, 'myWindow', 'width=' + width + ',height=' + height + ',top=' + top + ', left=' + left);

        let bc = new BroadcastChannel('infusionSoft_channel');
        bc.onmessage = function (event) {
            if (
                event.data?.type === 'sendInfusionSoftToken'
                && event.data.data?.code
            ) {
                InfusionSoftData['authorizeCode'] = event.data.data.code;
                self.setState({ infusionSoft: InfusionSoftData });
                self.requestAccessToken();
                bc.close();
            }
        }
    }

    /**
     * Renders HTML contents via components to integrate InfusionSoft.
     */
    render() {
        return (
            <div>
                <form className="forms-sample" id="autoresponder_from">
                    <label className="col-sm-12 col-form-label pl-0">
                        Enter Your Keap Client Id & Client Secret
                    </label>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="Keap Client Id"
                                value={this.state.infusionSoft.clientId}
                                disabled={this.state.disabled ? 'disabled' : ''}
                                onChange={this.handleChange.bind(this, 'clientId')}
                            />
                        </div>
                    </div>
                    <div className="form-group row">
                        <div className="col-sm-12">
                            <input
                                type="text"
                                className="form-control"
                                placeholder="Keap Client Secret"
                                value={this.state.infusionSoft.clientSecret}
                                disabled={this.state.disabled ? 'disabled' : ''}
                                onChange={this.handleChange.bind(this, 'clientSecret')}
                            />
                        </div>
                    </div>
                    <div className={`form-group row ${this.state.tags.length ? 'd-block' : 'd-none'}`}>
                        <label className="col-sm-12">(Optional) Apply A Tag:</label>
                        <Select
                            className="col-sm-12"
                            isMulti={true}
                            value={this.state.infusionSoft.activeTags}
                            onChange={this.handleSelectChange.bind(this, 'activeTags')}
                            options={
                                this.state.tags.map((item) => {
                                    return {
                                        label: `${item.name} (${item?.category?.name ?? 'Without category'})`,
                                        value: item.id,
                                    }
                                })
                            }
                        />
                    </div>

                    <div className="form-group row">
                        <div className="col-sm-12">
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={this.getInfusionSoft.bind(this)}
                                disabled={this.state.disabled ? 'disabled' : ''}
                            >
                                {this.state.disabled ? 'Checking...' : 'Save Integration'}
                            </button>
                        </div>
                    </div>
                    <div
                        className="alert alert-fill-danger"
                        role="alert"
                        style={{display: this.state.autoResponderError ? 'block' : 'none'}}
                    >
                        <span>{this.state.autoResponderError}</span>
                    </div>
                    <div
                        className="alert alert-fill-success"
                        role="alert"
                        style={{display: this.state.autoResponderSuccess ? 'block' : 'none'}}
                    >
                        <span>{this.state.autoResponderSuccess}</span>
                    </div>
                </form>
            </div>
        );
    }
}

export default InfusionSoft;
