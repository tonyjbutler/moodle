M.core_rating = {

    Y : null,
    api: M.cfg.wwwroot + '/rating/rate_ajax.php',

    init : function(Y){
        this.Y = Y;
        Y.delegate('change', this.submit_rating, Y.config.doc.body, 'select.postratingmenu', this);
        Y.delegate('click', this.submit_rating, Y.config.doc.body, 'input.togglebutton', this);

        // Hide the rating submit buttons.
        this.Y.all('input.postratingmenusubmit').setStyle('display', 'none');

        // Make the toggle submit buttons inactive.
        this.Y.all('input.togglebutton').setAttribute('type', 'button');
    },

    submit_rating: function(e) {
        var theinputs = e.target.ancestor('form').all('.ratinginput');
        var thedata = [];

        var inputssize = theinputs.size();
        for (var i = 0; i < inputssize; i++) {
            if(theinputs.item(i).get("name") != "returnurl") { // Dont include return url for ajax requests.
                thedata[theinputs.item(i).get("name")] = theinputs.item(i).get("value");
            }
        }

        // Add a JavaScript loading icon.
        var spinner = M.util.add_spinner(Y, e.target.ancestor('div'));
        spinner.removeClass('iconsmall');

        var scope = this;
        var cfg = {
            method: 'POST',
            on: {
                start: function() {
                    // Display the JS loading icon.
                    spinner.show();
                },
                complete : function(tid, outcome, args) {
                    try {
                        if (!outcome) {
                            alert('IO FATAL');
                            return false;
                        }

                        var data = scope.Y.JSON.parse(outcome.responseText);
                        if (data.success){
                            if (data.itemid) {
                                var itemid = data.itemid;

                                if (data.ui === 'button') {
                                    // Update the number of ratings.
                                    if (data.hasOwnProperty('count')) {
                                        var countnode = scope.Y.one('#ratingcount' + itemid);
                                        if (data.count > 0) {
                                            countnode.set('innerHTML', data.count);
                                        } else {
                                            countnode.set('innerHTML', '-');
                                        }
                                    }

                                    // Update toggle button value and class.
                                    var inputnode = scope.Y.one('#togglebuttoninput' + itemid);
                                    var value = inputnode.get('value');
                                    var submitnode = scope.Y.one('#togglebuttonsubmit' + itemid);
                                    switch(value) {
                                        case '-999':
                                            inputnode.set('value', 1);
                                            submitnode.removeClass('toggledon');
                                            submitnode.addClass('dimmed_text');
                                            submitnode.setAttribute('title', data.strtoggleon);
                                            break;
                                        default:
                                            inputnode.set('value', -999);
                                            submitnode.addClass('toggledon');
                                            submitnode.removeClass('dimmed_text');
                                            submitnode.setAttribute('title', data.strtoggleoff);
                                            break;
                                    }
                                } else {
                                    // If the user has access to the aggregate then update it.
                                    if (data.hasOwnProperty('aggregate')) {
                                        var aggregatenode = scope.Y.one('#ratingaggregate' + itemid);
                                        aggregatenode.set('innerHTML', data.aggregate);

                                        // Empty the count value if no ratings.
                                        var countnode = scope.Y.one('#ratingcount' + itemid);
                                        if (data.count > 0) {
                                            countnode.set('innerHTML', "(" + data.count + ")");
                                        } else {
                                            countnode.set('innerHTML', "");
                                        }
                                    }
                                }
                            }
                            return true;
                        }
                        else if (data.error) {
                            alert(data.error);
                        }
                    } catch(e) {
                        alert(e.message + " " + outcome.responseText);
                    }
                    return false;
                },
                end: function() {
                    // Hide the JS loading icon.
                    spinner.hide();
                }
            },
            arguments: {
                scope: scope
            },
            headers: {
            },
            data: build_querystring(thedata)
        };
        this.Y.io(this.api, cfg);

    }
};
