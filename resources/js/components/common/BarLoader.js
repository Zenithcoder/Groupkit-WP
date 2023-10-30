import React, { Component } from 'react';

/**
 * Show animated bars while some action is finished.
 */
export class BarLoader extends Component {
    render() {
        return (
            <div id="waitingoverlay">
                <div className="loader-demo-box sectionLoader">
                    <div className="bar-loader">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span className="integration-message-text">
                        {this.props.message}
                    </span>
                </div>
            </div>
        )
    }
}
