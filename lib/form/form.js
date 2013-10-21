/**
 * This file contains JS functionality required by mforms and is included automatically
 * when required.
 */

// Namespace for the form bits and bobs
M.form = M.form || {};

/**
 * Stores a list of the dependencyManager for each form on the page.
 */
M.form.dependencyManagers = {};

/**
 * Initialises a manager for a forms dependencies.
 * This should happen once per form.
 */
M.form.initFormDependencies = function(Y, formid, dependencies) {

    // If the dependencies isn't an array or object we don't want to
    // know about it
    if (!Y.Lang.isArray(dependencies) && !Y.Lang.isObject(dependencies)) {
        return false;
    }

    /**
     * Fixes an issue with YUI's processing method of form.elements property
     * in Internet Explorer.
     *     http://yuilibrary.com/projects/yui3/ticket/2528030
     */
    Y.Node.ATTRS.elements = {
        getter: function() {
            return Y.all(new Y.Array(this._node.elements, 0, true));
        }
    };

    // Define the dependency manager if it hasn't already been defined.
    M.form.dependencyManager = M.form.dependencyManager || (function(){
        var dependencyManager = function(config) {
            dependencyManager.superclass.constructor.apply(this, arguments);
        };
        dependencyManager.prototype = {
            _form : null,
// ou-specific begins #8250 (until Moodle 2.6)
/*
            _depElements : [],
            _nameCollections : [],
*/
            _locks : [],
            _hides : [],
            _dirty : [],
            _nameCollections : null,
            _fileinputs : null,
// ou-specific ends #8250 (until Moodle 2.6)
            initializer : function(config) {
                var i = 0, nodeName;
                this._form = Y.one('#'+formid);
                for (i in dependencies) {
// ou-specific begins #8250 (until Moodle 2.6)
/*
                    this._depElements[i] = this.elementsByName(i);
                    if (this._depElements[i].size() == 0) {
*/
                    var elements = this.elementsByName(i);
                    if (elements.size() == 0) {
// ou-specific ends #8250 (until Moodle 2.6)
                        continue;
                    }
// ou-specific begins #8250 (until Moodle 2.6)
/*
                    this._depElements[i].each(function(node){
*/
                    elements.each(function(node){
// ou-specific ends #8250 (until Moodle 2.6)
                        nodeName = node.get('nodeName').toUpperCase();
                        if (nodeName == 'INPUT') {
                            if (node.getAttribute('type').match(/^(button|submit|radio|checkbox)$/)) {
// ou-specific begins #8250 (until Moodle 2.6)
/*
                                node.on('click', this.checkDependencies, this);
*/
                                node.on('click', this.updateEventDependencies, this);
// ou-specific ends #8250 (until Moodle 2.6)
                            } else {
// ou-specific begins #8250 (until Moodle 2.6)
/*
                                node.on('blur', this.checkDependencies, this);
*/
                                node.on('blur', this.updateEventDependencies, this);
// ou-specific ends #8250 (until Moodle 2.6)
                            }
// ou-specific begins #8250 (until Moodle 2.6)
/*
                            node.on('change', this.checkDependencies, this);
*/
                            node.on('change', this.updateEventDependencies, this);
// ou-specific ends #8250 (until Moodle 2.6)
                        } else if (nodeName == 'SELECT') {
// ou-specific begins #8250 (until Moodle 2.6)
/*
                            node.on('change', this.checkDependencies, this);
*/
                            node.on('change', this.updateEventDependencies, this);
// ou-specific ends #8250 (until Moodle 2.6)
                        } else {
// ou-specific begins #8250 (until Moodle 2.6)
/*
                            node.on('click', this.checkDependencies, this);
                            node.on('blur', this.checkDependencies, this);
                            node.on('change', this.checkDependencies, this);
*/
                            node.on('click', this.updateEventDependencies, this);
                            node.on('blur', this.updateEventDependencies, this);
                            node.on('change', this.updateEventDependencies, this);
// ou-specific ends #8250 (until Moodle 2.6)
                        }
                    }, this);
                }
                this._form.get('elements').each(function(input){
                    if (input.getAttribute('type')=='reset') {
                        input.on('click', function(){
                            this._form.reset();
// ou-specific begins #8250 (until Moodle 2.6)
/*
                            this.checkDependencies();
*/
                            this.updateAllDependencies();
// ou-specific ends #8250 (until Moodle 2.6)
                        }, this);
                    }
                }, this);

// ou-specific begins #8250 (until Moodle 2.6)
/*
                return this.checkDependencies(null);
*/
                return this.updateAllDependencies();
            },
            /**
             * Initializes the mapping from element name to YUI NodeList
             */
            initElementsByName : function() {
                var names = [];
                // Collect element names
                for (var i in dependencies) {
                    names[i] = new Y.NodeList();
                    for (var condition in dependencies[i]) {
                        for (var value in dependencies[i][condition]) {
                            for (var ei in dependencies[i][condition][value]) {
                                names[dependencies[i][condition][value][ei]] = new Y.NodeList();
                            }
                        }
                    }
                }
                // Locate elements for each name
                this._form.get('elements').each(function(node){
                    var name = node.getAttribute('name');
                    if (names[name]) {
                        names[name].push(node);
                    }
                });
                this._nameCollections = names;
// ou-specific ends #8250 (until Moodle 2.6)
            },
            /**
             * Gets all elements in the form by their name and returns
             * a YUI NodeList
             * @return Y.NodeList
             */
            elementsByName : function(name) {
// ou-specific begins #8250 (until Moodle 2.6)
                if (!this._nameCollections) {
                    this.initElementsByName();
                }
// ou-specific ends #8250 (until Moodle 2.6)
                if (!this._nameCollections[name]) {
// ou-specific begins #8250 (until Moodle 2.6)
/*
                    var elements = [];
                    this._form.get('elements').each(function(){
                        if (this.getAttribute('name') == name) {
                            elements.push(this);
                        }
                    });
                    this._nameCollections[name] = new Y.NodeList(elements);
*/
                    return new Y.NodeList();
// ou-specific ends #8250 (until Moodle 2.6)
                }
                return this._nameCollections[name];
            },
            /**
             * Checks the dependencies the form has an makes any changes to the
             * form that are required.
             *
             * Changes are made by functions title _dependency_{dependencytype}
             * and more can easily be introduced by defining further functions.
             */
// ou-specific begins #8250 (until Moodle 2.6)
/*
            checkDependencies : function(e) {
                var tolock = [],
                    tohide = [],
                    dependon, condition, value,
                    lock, hide, checkfunction, result;
                for (dependon in dependencies) {
                    if (this._depElements[dependon].size() == 0) {
                        continue;
                    }
                    for (condition in dependencies[dependon]) {
                        for (value in dependencies[dependon][condition]) {
                            lock = false;
                            hide = false;
                            checkfunction = '_dependency_'+condition;
                            if (Y.Lang.isFunction(this[checkfunction])) {
                                result = this[checkfunction].apply(this, [this._depElements[dependon], value, e]);
                            } else {
                                result = this._dependency_default(this._depElements[dependon], value, e);
                            }
                            lock = result.lock || false;
                            hide = result.hide || false;
                            for (var ei in dependencies[dependon][condition][value]) {
                                var eltolock = dependencies[dependon][condition][value][ei];
                                if (hide) {
                                    tohide[eltolock] = true;
                                }
                                if (tolock[eltolock] != null) {
                                    tolock[eltolock] = lock || tolock[eltolock];
                                } else {
                                    tolock[eltolock] = lock;
                                }
                            }
*/
            checkDependencies : function(e, dependon) {
                var tohide = [],
                    tolock = [],
                    condition, value, lock, hide,
                    checkfunction, result, elements;
                if (!dependencies[dependon]) {
                    return true;
                }
                elements = this.elementsByName(dependon);
                for (condition in dependencies[dependon]) {
                    for (value in dependencies[dependon][condition]) {
                        checkfunction = '_dependency_'+condition;
                        if (Y.Lang.isFunction(this[checkfunction])) {
                            result = this[checkfunction].apply(this, [elements, value, e]);
                        } else {
                            result = this._dependency_default(elements, value, e);
                        }
                        lock = result.lock || false;
                        hide = result.hide || false;
                        for (var ei in dependencies[dependon][condition][value]) {
                            var eltolock = dependencies[dependon][condition][value][ei];
                            tohide[eltolock] = tohide[eltolock] || hide;
                            tolock[eltolock] = tolock[eltolock] || lock;
// ou-specific ends #8250 (until Moodle 2.6)
                        }
                    }
                }
                for (var el in tolock) {
// ou-specific begins #8250 (until Moodle 2.6)
/*
                    this._disableElement(el, tolock[el]);
                    if (tohide.propertyIsEnumerable(el)) {
                        this._hideElement(el, tohide[el]);
*/
                    var needsupdate = false;
                    if (tolock[el]) {
                        this._locks[el] = this._locks[el] || [];
                        if (!this._locks[el][dependon]) {
                            this._locks[el][dependon] = true;
                            needsupdate = true;
                        }
                    } else if (this._locks[el] && this._locks[el][dependon]) {
                        delete this._locks[el][dependon];
                        needsupdate = true;
                    }
                    if (tohide[el]) {
                        this._hides[el] = this._hides[el] || [];
                        if (!this._hides[el][dependon]) {
                            this._hides[el][dependon] = true;
                            needsupdate = true;
                        }
                    } else if (this._hides[el] && this._hides[el][dependon]) {
                        delete this._hides[el][dependon];
                        needsupdate = true;
                    }
                    if (needsupdate) {
                        this._dirty[el] = true;
// ou-specific ends #8250 (until Moodle 2.6)
                    }
                }
                return true;
            },
// ou-specific begins #8250 (until Moodle 2.6)
            /**
             * Update all dependencies in form
             */
            updateAllDependencies : function() {
                for (var el in dependencies) {
                    this.checkDependencies(null, el);
                }
                this.updateForm();
            },
            /**
             * Update dependencies associated with event
             *
             * @param {Event} e The event.
             */
            updateEventDependencies : function(e) {
                var el = e.target.getAttribute('name');
                this.checkDependencies(e, el);
                this.updateForm();
            },
            /**
             * Flush pending changes to the form
             */
            updateForm : function() {
                for (var el in this._dirty) {
                    if (this._locks[el]) {
                        var locked = !this._isObjectEmpty(this._locks[el]);
                        this._disableElement(el, locked);
                    }
                    if (this._hides[el]) {
                        var hidden = !this._isObjectEmpty(this._hides[el]);
                        this._hideElement(el, hidden);
                    }
                }
                this._dirty = [];
            },
// ou-specific ends #8250 (until Moodle 2.6)
            /**
             * Disabled all form elements with the given name
             */
            _disableElement : function(name, disabled) {
                var els = this.elementsByName(name);
// ou-specific begins #8250 (until Moodle 2.6)
/*
                var form = this;
                els.each(function(){
*/
                var filepicker = this.isFilePicker(name);
                els.each(function(node){
// ou-specific ends #8250 (until Moodle 2.6)
                    if (disabled) {
// ou-specific begins #8250 (until Moodle 2.6)
/*
                        this.setAttribute('disabled', 'disabled');
*/
                        node.setAttribute('disabled', 'disabled');
// ou-specific ends #8250 (until Moodle 2.6)
                    } else {
// ou-specific begins #8250 (until Moodle 2.6)
/*
                        this.removeAttribute('disabled');
*/
                        node.removeAttribute('disabled');
// ou-specific ends #8250 (until Moodle 2.6)
                    }

                    // Extra code to disable filepicker or filemanager form elements
// ou-specific begins #8250 (until Moodle 2.6)
/*
                    var fitem = this.ancestor('.fitem');
                    if (fitem && (fitem.hasClass('fitem_ffilemanager') || fitem.hasClass('fitem_ffilepicker'))) {
                        if (disabled){
                            fitem.addClass('disabled');
                        } else {
                            fitem.removeClass('disabled');
*/
                    if (filepicker) {
                        var fitem = node.ancestor('.fitem');
                        if (fitem) {
                            if (disabled){
                                fitem.addClass('disabled');
                            } else {
                                fitem.removeClass('disabled');
                            }
// ou-specific ends #8250 (until Moodle 2.6)
                        }
                    }
                })
            },
            /**
             * Hides all elements with the given name.
             */
            _hideElement : function(name, hidden) {
                var els = this.elementsByName(name);
// ou-specific begins #8250 (until Moodle 2.6)
/*
                els.each(function(){
                    var e = els.ancestor('.fitem');
*/
                els.each(function(node){
                    var e = node.ancestor('.fitem');
// ou-specific ends #8250 (until Moodle 2.6)
                    if (e) {
                        e.setStyles({
                            display : (hidden)?'none':''
                        })
                    }
                });
            },
// ou-specific begins #8250 (until Moodle 2.6)
            /**
             * Is the form element inside a filepicker or filemanager?
             *
             * @param {string} el The form element name.
             * @return {boolean}
             */
            isFilePicker : function(el) {
                if (!this._fileinputs) {
                    var fileinputs = [];
                    var els = this._form.all('.fitem.fitem_ffilepicker input,.fitem.fitem_ffilemanager input');
                    els.each(function(node){
                        fileinputs[node.getAttribute('name')] = true;
                    });
                    this._fileinputs = fileinputs;
                }
                return this._fileinputs[el] || false;
            },
            /**
             * Check if the object is empty
             *
             * @param {object} obj
             * @return {boolean}
             */
            _isObjectEmpty : function(obj) {
                for(var prop in obj) {
                    if(obj.hasOwnProperty(prop))
                        return false;
                }
                return true;
            },
// ou-specific ends #8250 (until Moodle 2.6)
            _dependency_notchecked : function(elements, value) {
                var lock = false;
                elements.each(function(){
                    if (this.getAttribute('type').toLowerCase()=='hidden' && !this.siblings('input[type=checkbox][name="' + this.get('name') + '"]').isEmpty()) {
                        // This is the hidden input that is part of an advcheckbox.
                        return;
                    }
                    if (this.getAttribute('type').toLowerCase()=='radio' && this.get('value') != value) {
                        return;
                    }
                    lock = lock || !Y.Node.getDOMNode(this).checked;
                });
                return {
                    lock : lock,
                    hide : false
                }
            },
            _dependency_checked : function(elements, value) {
                var lock = false;
                elements.each(function(){
                    if (this.getAttribute('type').toLowerCase()=='hidden' && !this.siblings('input[type=checkbox][name="' + this.get('name') + '"]').isEmpty()) {
                        // This is the hidden input that is part of an advcheckbox.
                        return;
                    }
                    if (this.getAttribute('type').toLowerCase()=='radio' && this.get('value') != value) {
                        return;
                    }
                    lock = lock || Y.Node.getDOMNode(this).checked;
                });
                return {
                    lock : lock,
                    hide : false
                }
            },
            _dependency_noitemselected : function(elements, value) {
                var lock = false;
                elements.each(function(){
                    lock = lock || this.get('selectedIndex') == -1;
                });
                return {
                    lock : lock,
                    hide : false
                }
            },
            _dependency_eq : function(elements, value) {
                var lock = false;
                var hidden_val = false;
                var options, v, selected, values;
                elements.each(function(){
                    if (this.getAttribute('type').toLowerCase()=='radio' && !Y.Node.getDOMNode(this).checked) {
                        return;
                    } else if (this.getAttribute('type').toLowerCase() == 'hidden' && !this.siblings('input[type=checkbox][name="' + this.get('name') + '"]').isEmpty()) {
                        // This is the hidden input that is part of an advcheckbox.
                        hidden_val = (this.get('value') == value);
                        return;
                    } else if (this.getAttribute('type').toLowerCase() == 'checkbox' && !Y.Node.getDOMNode(this).checked) {
                        lock = lock || hidden_val;
                        return;
                    }
                    if (this.getAttribute('class').toLowerCase() == 'filepickerhidden') {
                        // Check for filepicker status.
                        var elementname = this.getAttribute('name');
                        if (elementname && M.form_filepicker.instances[elementname].fileadded) {
                            lock = false;
                        } else {
                            lock = true;
                        }
                    } else if (this.get('nodeName').toUpperCase() === 'SELECT' && this.get('multiple') === true) {
                        // Multiple selects can have one or more value assigned. A pipe (|) is used as a value separator
                        // when multiple values have to be selected at the same time.
                        values = value.split('|');
                        selected = [];
                        options = this.get('options');
                        options.each(function() {
                            if (this.get('selected')) {
                                selected[selected.length] = this.get('value');
                            }
                        });
                        if (selected.length > 0 && selected.length === values.length) {
                            for (var i in selected) {
                                v = selected[i];
                                if (values.indexOf(v) > -1) {
                                    lock = true;
                                } else {
                                    lock = false;
                                    return;
                                }
                            }
                        } else {
                            lock = false;
                        }
                    } else {
                        lock = lock || this.get('value') == value;
                    }
                });
                return {
                    lock : lock,
                    hide : false
                }
            },
            _dependency_hide : function(elements, value) {
                return {
                    lock : false,
                    hide : true
                }
            },
            _dependency_default : function(elements, value, ev) {
                var lock = false;
                var hidden_val = false;
                elements.each(function(){
                    if (this.getAttribute('type').toLowerCase()=='radio' && !Y.Node.getDOMNode(this).checked) {
                        return;
                    } else if (this.getAttribute('type').toLowerCase() == 'hidden' && !this.siblings('input[type=checkbox][name="' + this.get('name') + '"]').isEmpty()) {
                        // This is the hidden input that is part of an advcheckbox.
                        hidden_val = (this.get('value') != value);
                        return;
                    } else if (this.getAttribute('type').toLowerCase() == 'checkbox' && !Y.Node.getDOMNode(this).checked) {
                        lock = lock || hidden_val;
                        return;
                    }
                    //check for filepicker status
                    if (this.getAttribute('class').toLowerCase() == 'filepickerhidden') {
                        var elementname = this.getAttribute('name');
                        if (elementname && M.form_filepicker.instances[elementname].fileadded) {
                            lock = true;
                        } else {
                            lock = false;
                        }
                    } else if (this.get('nodeName').toUpperCase() === 'SELECT' && this.get('multiple') === true) {
                        // Multiple selects can have one or more value assigned. A pipe (|) is used as a value separator
                        // when multiple values have to be selected at the same time.
                        values = value.split('|');
                        selected = [];
                        options = this.get('options');
                        options.each(function() {
                            if (this.get('selected')) {
                                selected[selected.length] = this.get('value');
                            }
                        });
                        if (selected.length > 0 && selected.length === values.length) {
                            for (var i in selected) {
                                v = selected[i];
                                if (values.indexOf(v) > -1) {
                                    lock = false;
                                } else {
                                    lock = true;
                                    return;
                                }
                            }
                        } else {
                            lock = true;
                        }
                    } else {
                        lock = lock || this.get('value') != value;
                    }
                });
                return {
                    lock : lock,
                    hide : false
                }
            }
        };
        Y.extend(dependencyManager, Y.Base, dependencyManager.prototype, {
            NAME : 'mform-dependency-manager'
        });

        return dependencyManager;
    })();

    M.form.dependencyManagers[formid] = new M.form.dependencyManager();
    return M.form.dependencyManagers[formid];
};

/**
 * Update the state of a form. You need to call this after, for example, changing
 * the state of some of the form input elements in your own code, in order that
 * things like the disableIf state of elements can be updated.
 */
M.form.updateFormState = function(formid) {
    if (formid in M.form.dependencyManagers) {
// ou-specific begins #8250 (until Moodle 2.6)
/*
        M.form.dependencyManagers[formid].checkDependencies(null);
*/
        M.form.dependencyManagers[formid].updateAllDependencies();
// ou-specific ends #8250 (until Moodle 2.6)
    }
};
