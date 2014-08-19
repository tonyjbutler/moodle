M.assignsubmission_file = {
    _checkboxnameregex: /^.+\[([^\]]+)\]$/,

    handle_filetype_change: function (changeel, fieldset) {
        var namebits = changeel.get('name').match(this._checkboxnameregex);
        if (namebits === null) {
            return;
        }
        var fullname = namebits[0], typename = namebits[1];
        var ischecked = changeel.get('checked');

        if (typename.indexOf('/') >= 0) {
            // Single type checkbox toggled, so update any other instances having
            // the same name to be the same state.
            fieldset.all('input[type=checkbox]').each(function (el) {
                if (el.get('name') === fullname) {
                    el.set('checked', ischecked);
                }
            });
        } else {
            // Group checkbox (i.e. non-mimetype) toggled, so update its related children.
            this.update_filegroup_children(typename, fieldset, ischecked);
        }
    },

    update_filegroup_children: function (typename, fieldset, ischecked) {
        fieldset.all('input[data-group=' + typename + ']').each(function (el) {
            var span = el.ancestor('span')
            if (ischecked) {
                span.addClass('group-selected');
            } else {
                span.removeClass('group-selected');
            }
        });
    },

    init_filetypes_config: function (Y) {
        var fieldset = Y.one('#fgroup_id_assignsubmission_file_filetypes fieldset');

        // Catch any checkbox change events.
        fieldset.delegate('change', function (e) {
            this.handle_filetype_change(e.target, fieldset);
        }, 'input[type=checkbox]', this);

        // Set the initial group selection state for child checkboxes.
        fieldset.all('input[type=checkbox]').each(function (el) {
            if (!el.get('checked')) {
                return;
            }
            var namebits = el.get('name').match(this._checkboxnameregex);
            if (namebits === null || namebits[1].indexOf('/') >= 0) {
                return;
            }
            this.update_filegroup_children(namebits[1], fieldset, true);
        }.bind(this));
    }
};
