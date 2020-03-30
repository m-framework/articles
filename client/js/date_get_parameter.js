if (typeof m == 'undefined') {
    var m = function () {};
    m.fn = m.prototype = {};
}

m.fn.date_get_parameter = function(context) {
    var
        field = this,
        name = this.attr('name') || this.data.name,
        path =  this.data.path || window.location.pathname,
        r = new RegExp('/' + name + '/[a-z0-9\-\_\.]+', 'gi'),
        page_r = new RegExp('/page/[0-9]+', 'gi'),
        location_update = function(set){

            if (path.match(r) !== null) {
                path = path.replace(r, '');
            }
            if (path.match(page_r) !== null) {
                path = path.replace(page_r, '');
            }

            if (set === true) {
                path = path + '/' + name + '/' + this.value;
            }

            window.location.href = path;
        };

    this.on('change', function(){

        var st = this.tagName == 'INPUT' && this.getAttribute('type') !== null
            && ((['checkbox', 'radio'].indexOf(this.getAttribute('type')) > -1 && !this.checked)
            || this.value.trim().length == 0);

        location_update.call(this, !st);
    });

    return true;
};
