// --------------------------------------------------------------------
// crossbrowser Stylesheet Handler
//
// Copyright 2001 by Christian Mueller
// Email: christian.mueller@europe.com
//
// License Agreement
// 
// Except as specifically authorized from me, you may not make copies
// of Software, other than a single copy of Software for archival
// purposes. Unless enforcement is prohibited by applicable law, you
// may not modify or publish this Software. 
//
// example for set: 
// var css = new StyleSheet( 'body', CSS_TAG );
// css.set( 'background-color', 'FF0000', 'color', 'FFFFFF' );

// example for remove: 
// var css = new StyleSheet( 'body', CSS_TAG );
// css.remove();
// --------------------------------------------------------------------

var CSS_ID = 0, CSS_CLASS = 1, CSS_TAG = 2;

function StyleSheet( name, type, debug ) {

  this.VERSION = '1.01';
  
  this.add = _add;
  this.set = _set;
  this.find = _find;
  this.init = _init;
  this.remove = _remove;
  
  this.debug = debug;
  this.init( name, type, 1 );
  
  function _init( name, type, reset ) {
    this.id = name;
    this.type = ! isNaN( type ) ? type : 0; // 0: id, 1: class, 2: tag
    if( reset ) this.items = new Array();
    if( this.debug && ! this.debugForm ) {
      this.debugForm = window.open( "", "Debug", "width=640,height=480,status=yes,scrollbars=yes,locationbar=no" );
      this.debugForm.document.write( '<body bgcolor="#000000" text="#FFFFFF"><font face="Arial" size="2">' );
    }
  }
  
  function _add() {
    var i, j, len = parseInt( arguments.length / 2 ) * 2;
    for( i = 0; i < len; i += 2 ) {
      if( ( j = this.find( arguments[i] ) ) < 0 ) {
        this.items[this.items.length] = arguments[i];
        this.items[this.items.length] = arguments[i+1];
      }
      else {
        this.items[j+1] = arguments[i+1];
      }
    }
  }
  
  function _find( property ) {
    for( var i = 0; i < this.items.length; i += 2 ) {
      if( this.items[i] == property ) return i;
    }
    return -1;
  }
  
  function _set() {
    var i, j, len, tmp, res;
    len = parseInt( arguments.length / 2 ) * 2;
    for( i = 0; i < len; i += 2 ) {
      if( ( j = this.find( arguments[i] ) ) < 0 ) {
        this.items[this.items.length] = arguments[i];
        this.items[this.items.length] = arguments[i+1];
      }
      else {
        this.items[j+1] = arguments[i+1];
      }
    }
    if( ! this.items.length ) return true;
    if( document.classes && document.tags && document.ids ) { // NN
      switch( this.type ) {
      case 0:
      default:
        for( i = 0; i < this.items.length; i += 2 ) {
          tmp = _cssType( _styleNn( this.items[i] ), 2 );
          document.ids[this.id][tmp] = _styleCol( this.items[i], this.items[i+1] );
          if( this.debug && this.debugForm )
            this.debugForm.document.write( 'NN: ' + 'document.ids[' + this.id + '][' + tmp + '] = ' + _styleCol( this.items[i], this.items[i+1] ) + '<hr size=1>' );
        }
        break;
      case 1:
        for( i = 0; i < this.items.length; i += 2 ) {
          tmp = _cssType( _styleNn( this.items[i] ), 2 );
          document.classes[this.id].all[tmp] = _styleCol( this.items[i], this.items[i+1] );
          if( this.debug && this.debugForm )
            this.debugForm.document.write( 'NN: ' + 'document.classes[' + this.id + '].all[' + tmp + '] = ' + _styleCol( this.items[i], this.items[i+1] ) + '<hr size=1>' );
        }
        break;
      case 2:
        for( i = 0; i < this.items.length; i += 2 ) {
          tmp = _cssType( _styleNn( this.items[i] ), 2 );
          document.tags[this.id][tmp] = _styleCol( this.items[i], this.items[i+1] );
          if( this.debug && this.debugForm )
            this.debugForm.document.write( 'NN: ' + 'document.tags[' + this.id + '][' + tmp + '] = ' + _styleCol( this.items[i], this.items[i+1] ) + '<hr size=1>' );
        }
        break;
      }
    }
    else if( document.styleSheets && document.styleSheets.length ) { // DOM compatible
      for( i = 0, tmp = ''; i < this.items.length; i += 2 ) {
        tmp += _styleDom( this.items[i] ) + ':' + _styleCol( this.items[i], this.items[i+1] ) + ';';
      }
      if( document.styleSheets[0].insertRule ) { // DOM original
        if( this.debug && this.debugForm )
          this.debugForm.document.write( 'DOM: ' + _cssType( this.id, this.type ) + '{' + tmp + '}' + '<hr size=1>' );
        document.styleSheets[0].insertRule( _cssType( this.id, this.type ) + '{' + tmp + '}', 0 );
      }
      else if( document.all ) { // IE5+
        if( this.debug && this.debugForm )
          this.debugForm.document.write( 'IE: ' + _cssType( this.id, this.type ) + ' - ' + tmp + '<hr size=1>' );
        document.styleSheets[0].addRule( _cssType( this.id, this.type ), tmp, 0 );
      }
    }
    else return false;
    return true;
  }
  
  function _remove( index ) {
    if( document.classes && document.tags ) { // nn4
      // nothing to do here
    }
    else if( document.styleSheets && document.styleSheets.length ) { // dom compatible
      if( isNaN( index ) ) {
        index = -1;
        var tmp = _cssType( this.id, this.type );
        var rules = document.styleSheets[0].cssRules ? document.styleSheets[0].cssRules : document.styleSheets[0].rules;
        for( var i = 0; i < rules.length; i ++ ) {
          if( tmp == rules[i].selectorText ) {
            index = i;
            break;
          }
        }
      }
      if( isNaN( index ) || index < 0 ) return false;
      if( document.styleSheets[0].deleteRule ) {
        document.styleSheets[0].deleteRule( index );
      }
      else if( document.styleSheets[0].removeRule ) {
        document.styleSheets[0].removeRule( index );
      }
      else return false;
    }
    else return false;
    return true;
  }
  
}

function _cssType( id, type ) {
  switch( type ) {
  case 0: // id
    return ( id[0] != '#' ) ? '#' + id : id;
  case 1: // class
    return ( id[0] != '.' ) ? '.' + id : id;
  case 2: // tag, nn
    return ( id[0] == '#' || id[0] == '.' ) ? id.substr( 1, 0xFFFF ) : id;
  default:
    return id;
  }
}

function _styleNn( id ) {
  var res = '';
  for( var i = 0; i < id.length - 1; i ++ ) {
    if( id[i] == '-' ) {
      i ++;
      if( i < id.length ) res += id.substr( i, 1 ).toUpperCase();
    }
    else
      res += id.substr( i, 1 );
  }
  return ( res + id.substr( i, 1 ) );
}

function _styleDom( id ) {
  var res = '';
  for( var i = 0; i < id.length; i ++ ) {
    if( id[i] >= 'A' && id[i] <= 'Z' )
      res += '-' + id.substr( i, 1 ).toLowerCase();
    else
      res += id.substr( i, 1 );
  }
  return res;
}

function _styleCol( id, val ) {
  if( id.search( /color/i ) >= 0 ) {
    if( val[0] != '#' ) return '#' + val;
  }
  return val;
}

