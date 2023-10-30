import React, { Component } from 'react';
import Modal from 'react-bootstrap/Modal';
import * as Constants from '../Constants';

/**
 * Component used to display modal dialog and notify a user to install the GK extension
 * if it is not installed.
 *
 * @property {boolean} showInstallExtensionModal if true show the modal, otherwise hide it.
 * @property {Function} closeGenerateFacebookTagsModal for closing the GenerateFacebookTagsModal,
 * InstallExtensionModal modals, and reset the page state to the default value.
 *
 * @returns {JSX.Element} as HTML for modal window described above.
 */
export class InstallExtensionModal extends Component {
    render() {
        return (
            <Modal
                size="lg"
                dialogClassName="extensionModal"
                show={this.props.showInstallExtensionModal}
                onHide={this.props.closeGenerateFacebookTagsModal}
                backdrop="static"
                keyboard={false}
            >
                <Modal.Header className="border-0 p-0 m-0" closeButton>
                </Modal.Header>
                <Modal.Body>
                    <div className="row text-center">
                        <div className="col-sm-12 col-md-12">
                            <img className="chrome-web-store" src="/asset/images/chromewebstore-en.png" />
                        </div>
                        <div className="col-sm-12 col-md-12 label_text">
                            In order to use this feature, you need to install the extension from Chrome webstore.
                        </div>
                        <div className="col-sm-12 col-md-12 p-3">
                            <a
                                className="btn btn-sm btn-primary extension-btn"
                                href={Constants.EXTENSION_URL}
                                target="_blank"
                            >
                                INSTALL EXTENSION
                            </a>
                        </div>
                    </div>
                </Modal.Body>
            </Modal>
        )
    }
}
