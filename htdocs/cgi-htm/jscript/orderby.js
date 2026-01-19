ordir = location.search;
var exp = /ordir=(\w+)/;
if( exp.exec( ordir ) ) ordir = RegExp.$1; else ordir = 'asc';
order = location.search;
exp = /order=(.*?)\&/;
if( exp.exec( order ) ) order = RegExp.$1; else {
  exp = /order=(.*)/;
  if( exp.exec( order ) ) order = RegExp.$1; else order = '';
}

function orderBy( field ) {
  if( order == field ) {
    if( ordir.match( /desc/i ) ) ordir = 'asc'; else ordir = 'desc';
  }
  else
    ordir = 'asc';
  window.location.href = url_file + '&order=' + field + '&ordir=' + ordir;
}

function drawOrder( field, name ) {
  var out = '<td class="txt"><a href="javascript:orderBy(\'' + field + '\');"><b>' + name + '</b></a>';
  if( field == order ) {
    out += '&nbsp;<img src="' + url_images + '/order_' + ordir + '.gif">';
  }
  out += '</td>';
  document.open();
  document.write( out );
  document.close();
}

function linkOrder( field, name ) {
  document.open();
  document.write( '<a href="javascript:orderBy(\'' + field + '\');"><b>' + name + '</b></a>' );
  document.close();
}

function displayOrder( field, html_asc, html_desc, html_no ) {
  document.open();
  if( field == order ) {
    if( ordir == 'desc' )
      document.write( html_desc );
    else
      document.write( html_asc );
  }
  else
    document.write( html_no );
  document.close();
}
