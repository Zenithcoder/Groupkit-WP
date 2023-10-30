import React, {Component} from "react";

/**
 * Recommended Tags component handle adding/deleting recommended tags to/from group
 */
class RecommendedTags extends Component {

    /**
     * Initializes state and props for the component
     *
     * @param {object} props defined on the component tag
     */
    constructor(props) {
        super(props);

        this.state = {
            tags_to_add: [],
            tags_input: '',
        }
    }

    /**
     * Adds new recommended tag from the input
     */
    addTags() {
        if (
            this.state.tags_input
            && !this.state.tags_to_add.includes(this.state.tags_input)
        ) {
            const tagsToAdd = [...this.state.tags_to_add, this.state.tags_input];

            this.setState({
                tags_to_add: tagsToAdd,
                tags_input: '',
            });

            this.props.addTag(tagsToAdd);
        }
    }

    /**
     * Removes tag from the group recommended tags
     *
     * @param {String} removedTag
     */
    removeTag(removedTag) {
        this.setState({
            tags_to_add: this.state.tags_to_add.filter(tag => tag !== removedTag),
        });
        this.props.removeTag(removedTag);
    }

    /**
     * Deletes all recommended tags from group
     */
    deleteAllTags() {
        this.props.removeTags();
        this.setState({tags_to_add: []});
    }

    /**
     * Sets recommended tags from the provided tags props
     */
    setTags() {
        let tags = [];

        this.props.tags.forEach((tag) => {
            if (!tags.includes(tag.label)) {
                tags.push(tag.label);
            }
        });

        this.setState({ tags_to_add: [...tags] });
    }

    /**
     * Handles pressed keys to catch enter and submit input value
     *
     * @param {KeyboardEvent} event from the HTML input component
     */
    handleKeyDown(event) {
        if (event.key === 'Enter') {
            this.setState({ tags_input: event.target.value });
            this.addTags();
        }
    }

    /**
     * Sends provided tag to the props @onTagClick
     *
     * @param {String} tag to sent
     */
    fireTagClick(tag) {
        this.props.onTagClick(tag);
    }

    /**
     * Sets group recommended tags when component load
     */
    componentDidMount() {
        this.setTags();
    }

    /**
     * Returns HTML content of the component
     *
     * @return {JSX.Element} containing HTML with bind data
     */
    render() {
        return (
            <div>
                <div className="form-group row notes">
                    <label className="col-sm-3 col-form-label">Shortcut Tags</label>
                    <div className="col-sm-9">
                        <span className="flex items-center flex-wrap">
                        {
                            this.state.tags_to_add.map((tag, index) => {
                                return (
                                    <div key={`bulk-manage-shortcut-tag-${index}`} className="flex">
                                        <label className="default_Tag" onClick={this.fireTagClick.bind(this, tag)}>
                                            {tag}
                                        </label>
                                        <span onClick={this.removeTag.bind(this, tag)} className="tag-close-btn">
                                          x
                                        </span>
                                        <span className="self-center">|</span>
                                    </div>
                                );
                            })
                        }
                            {
                                this.state.tags_to_add.length
                                    ? (
                                        <label onClick={this.deleteAllTags.bind(this)}
                                               className="default_Tag clear-all-tags">
                                            Clear All
                                        </label>
                                    )
                                    : null
                            }
                        </span>
                    </div>
                </div>
                <div className="form-group row">
                    <div className="col-9">
                        <input
                            type="text"
                            className="form-control"
                            placeholder="Add shortcut tag..."
                            value={this.state.tags_input}
                            onChange={event => {this.setState({ tags_input: event.target.value })}}
                            onKeyDown={this.handleKeyDown.bind(this)}
                        />
                    </div>
                    <div className="col-3">
                        <button
                            type="button"
                            className="btn btn-primary add-recommended-tag"
                            onClick={this.addTags.bind(this)}
                        >
                            + Add
                        </button>
                    </div>
                </div>
            </div>
        );
    }
}

export default RecommendedTags;
