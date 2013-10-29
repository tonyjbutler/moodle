YUI.add('moodle-backup-backupselectall', function(Y) {

// Namespace for the backup
M.core_backup = M.core_backup || {};

/**
 * Adds select all/none links to the top of the backup/restore/import schema page.
 */
// ou-specific begins #8250 (until 2.6)
/*
M.core_backup.select_all_init = function(str) {
*/
M.core_backup.select_all_init = function(modnames) {
// ou-specific ends #8250 (until 2.6)
    var formid = null;

// ou-specific begins #8250 (until 2.6)
/*
    var helper = function(e, check, type) {
*/
    var helper = function(e, check, type, mod) {
// ou-specific ends #8250 (until 2.6)
        e.preventDefault();
// ou-specific begins #8250 (until 2.6)
        var prefix = '';
        if (typeof mod !== 'undefined') {
            prefix = 'setting_activity_' + mod + '_';
        }
// ou-specific ends #8250 (until 2.6)

        var len = type.length;
        Y.all('input[type="checkbox"]').each(function(checkbox) {
            var name = checkbox.get('name');
// ou-specific begins #8250 (until 2.6)
            // If a prefix has been set, ignore checkboxes which don't have that prefix.
            if (prefix && name.substring(0, prefix.length) !== prefix) {
                return;
            }
// ou-specific ends #8250 (until 2.6)
            if (name.substring(name.length - len) == type) {
                checkbox.set('checked', check);
            }
        });

        // At this point, we really need to persuade the form we are part of to
        // update all of its disabledIf rules. However, as far as I can see,
        // given the way that lib/form/form.js is written, that is impossible.
        if (formid && M.form) {
            M.form.updateFormState(formid);
        }
    };

// ou-specific begins #8250 (until 2.6)
/*
    var html_generator = function(classname, idtype) {
*/
    var html_generator = function(classname, idtype, heading, extra) {
        if (typeof extra === 'undefined') {
            extra = '';
        }
// ou-specific ends #8250 (until 2.6)
        return '<div class="' + classname + '">' +
// ou-specific begins #8250 (until 2.6)
/*
                    '<div class="fitem fitem_fcheckbox">' +
                        '<div class="fitemtitle">' + str.select + '</div>' +
*/
                    '<div class="fitem fitem_fcheckbox backup_selector">' +
                        '<div class="fitemtitle">' + heading + '</div>' +
// ou-specific ends #8250 (until 2.6)
                        '<div class="felement">' +
// ou-specific begins #8250 (until 2.6)
/*
                            '<a id="backup-all-' + idtype + '" href="#">' + str.all + '</a> / ' +
                            '<a id="backup-none-' + idtype + '" href="#">' + str.none + '</a>' +
*/
                            '<a id="backup-all-' + idtype + '" href="#">' + M.util.get_string('all', 'moodle') + '</a> / ' +
                            '<a id="backup-none-' + idtype + '" href="#">' + M.util.get_string('none', 'moodle') + '</a>' +
                            extra +
// ou-specific ends #8250 (until 2.6)
                        '</div>' +
                    '</div>' +
                '</div>';
    };

    var firstsection = Y.one('fieldset#id_coursesettings .fcontainer.clearfix .grouped_settings.section_level');
    if (!firstsection) {
        // This is not a relevant page.
        return;
    }
    if (!firstsection.one('.felement.fcheckbox')) {
        // No checkboxes.
        return;
    }

    formid = firstsection.ancestor('form').getAttribute('id');

    var withuserdata = false;
    Y.all('input[type="checkbox"]').each(function(checkbox) {
        var name = checkbox.get('name');
        if (name.substring(name.length - 9) == '_userdata') {
            withuserdata = '_userdata';
        } else if (name.substring(name.length - 9) == '_userinfo') {
            withuserdata = '_userinfo';
        }
    });

// ou-specific begins #8250 (until 2.6)
/*
    var html = html_generator('include_setting section_level', 'included');
*/
    // Add global select all/none options.
    var html = html_generator('include_setting section_level', 'included', M.util.get_string('select', 'moodle'),
            ' (<a id="backup-bytype" href="#">' + M.util.get_string('showtypes', 'backup') + '</a>)');
// ou-specific ends #8250 (until 2.6)
    if (withuserdata) {
// ou-specific begins #8250 (until 2.6)
/*
        html += html_generator('normal_setting', 'userdata');
*/
        html += html_generator('normal_setting', 'userdata', M.util.get_string('select', 'moodle'));
// ou-specific ends #8250 (until 2.6)
    }
    var links = Y.Node.create('<div class="grouped_settings section_level">' + html + '</div>');
    firstsection.insert(links, 'before');

// ou-specific begins #8250 (until 2.6)
    // Add select all/none for each module type.
    var initlinks = function(links, mod) {
        Y.one('#backup-all-mod_' + mod).on('click', function(e) { helper(e, true, '_included', mod); });
        Y.one('#backup-none-mod_' + mod).on('click', function(e) { helper(e, false, '_included', mod); });
        if (withuserdata) {
            Y.one('#backup-all-userdata-mod_' + mod).on('click', function(e) { helper(e, true, withuserdata, mod); });
            Y.one('#backup-none-userdata-mod_' + mod).on('click', function(e) { helper(e, false, withuserdata, mod); });
        }
    };

    // For each module type on the course, add hidden select all/none options.
    var modlist = Y.Node.create('<div id="mod_select_links">');
    modlist.hide();
    modlist.currentlyshown = false;
    links.appendChild(modlist);
    for (var mod in modnames) {
        // Only include actual values from the list.
        if (!modnames.hasOwnProperty(mod)) {
            continue;
        }
        var html = html_generator('include_setting section_level', 'mod_' + mod, modnames[mod]);
        if (withuserdata) {
            html += html_generator('normal_setting', 'userdata-mod_' + mod, modnames[mod]);
        }
        var modlinks = Y.Node.create(
            '<div class="grouped_settings section_level">' + html + '</div>');
        modlist.appendChild(modlinks);
        initlinks(modlinks, mod);
    }

    // Toggles the display of the hidden module select all/none links.
    var toggletypes = function() {
        // Change text of type toggle link.
        var link = Y.one('#backup-bytype');
        if (modlist.currentlyshown) {
            link.setHTML(M.util.get_string('showtypes', 'backup'));
        } else {
            link.setHTML(M.util.get_string('hidetypes', 'backup'));
        }

        // The link has now been toggled (from show to hide, or vice-versa).
        modlist.currentlyshown = !modlist.currentlyshown;

        // Either hide or show the links.
        var animcfg = { node: modlist, duration: 0.2 };
        if (modlist.currentlyshown) {
            // Animate reveal of the module links.
            modlist.show();
            animcfg.to = { maxHeight: modlist.get('clientHeight') + 'px' };
            modlist.setStyle('maxHeight', '0px');
            var anim = new Y.Anim(animcfg);
            anim.on('end', function() { modlist.setStyle('maxHeight', 'none'); });
            anim.run();
        } else {
            // Animate hide of the module links.
            animcfg.to = { maxHeight: '0px' };
            modlist.setStyle('maxHeight', modlist.get('clientHeight') + 'px');
            var anim = new Y.Anim(animcfg);
            anim.on('end', function() { modlist.hide(); modlist.setStyle('maxHeight', 'none'); });
            anim.run();
        }

    };
    Y.one('#backup-bytype').on('click', function(e) { toggletypes(); });

// ou-specific ends #8250 (until 2.6)
    Y.one('#backup-all-included').on('click',  function(e) { helper(e, true,  '_included'); });
    Y.one('#backup-none-included').on('click', function(e) { helper(e, false, '_included'); });
    if (withuserdata) {
        Y.one('#backup-all-userdata').on('click',  function(e) { helper(e, true,  withuserdata); });
        Y.one('#backup-none-userdata').on('click', function(e) { helper(e, false, withuserdata); });
    }
}

// ou-specific begins #8250 (until 2.6)
/*
}, '@VERSION@', {'requires':['base','node','event', 'node-event-simulate']});
*/
}, '@VERSION@', {'requires':['base', 'node', 'event', 'node-event-simulate', 'anim']});
// ou-specific ends #8250 (until 2.6)
