// --------------------------------------------------------------------
// Copyright 2001 by Christian Mueller
// Email: christian.mueller@europe.com
//
// License Agreement
// 
// Except as specifically authorized from me, you may not make copies
// of Software, other than a single copy of Software for archival
// purposes. Unless enforcement is prohibited by applicable law, you
// may not modify or publish this Software. 
// --------------------------------------------------------------------

function GetCookie( name ){
  var cname, dc, begin, end;
  /*if( ! navigator.cookieEnabled ) {
    alert( 'You must enable cookies, to use this function' );
    return false;
  }*/
  cname = name + "=";
  dc = document.cookie;
  if( ! dc.length || ( begin = dc.indexOf( cname ) ) < 0 ) return null;
  begin += cname.length;
  end = dc.indexOf( ";", begin );
  if( end == -1 ) end = dc.length;
  return unescape( dc.substring( begin, end ) );
}

function SetCookie( name, value, expires, session ) {
  var ses = "";
  /*if( ! navigator.cookieEnabled ) {
    alert( 'You must enable cookies, to use this function' );
    return false;
  }*/
  if( session ) expires = null;
  else if( ! expires ) expires = new Date();
  if( expires ) {
    // Wednesday, 4-11-01 12:44:3 GMT
    var days = new Array( "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday" );
    ses = "; expires=";
    ses += days[expires.getDay()] + ", ";
    ses += expires.getDate() + "-" + expires.getMonth() + "-";
    var year = new String( expires.getYear() + 1 );
    ses += year.substr( year.length - 2, 2 ) + " ";
    ses += expires.getHours() + ":" + expires.getMinutes() + ":" + expires.getSeconds() + " GMT";
  }
  document.cookie = name + "=" + escape( value ) + ses + "; path=/";
}

function findNodeByName( node, nodeName, name ) {
  if( ! node.childNodes || ( ! nodeName && ! name ) ) return null;
  var found, i;
  if( nodeName ) {
    for( i = 0; i < node.childNodes.length; i ++ ) {
      if( node.childNodes[i].nodeName == nodeName ) {
        if( ! name || ( name && node.childNodes[i].name == name ) )
          return node.childNodes[i];
      }
      found = findNodeByName( node.childNodes[i], nodeName, name );
      if( found ) return found;
    }
  }
  else {
    for( i = 0; i < node.childNodes.length; i ++ ) {
      if( node.childNodes[i].name == name ) return node.childNodes[i];
      found = findNodeByName( node.childNodes[i], nodeName, name );
      if( found ) return found;
    }
  }
  return null;
}
