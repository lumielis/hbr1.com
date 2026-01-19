// --------------------------------------------------------------------
// Vector class
// compatible to Netscape 4 Array object
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
// --------------------------------------------------------------------

function Vector() {

  // read the javascript 1.3 client documentation
  // from http://developer.netscape.com
  this.concat   = _concat;
  this.join     = _join;
  this.pop      = _pop;
  this.push     = _push;
  this.reverse  = _reverse;
  this.shift    = _shift;
  this.slice    = _slice;
  this.splice   = _splice;
  this.sort     = _sort;
  this.unshift  = _unshift;

  // get( index ) ... buf[index]
  this.get      = _get;
  
  if( arguments.length == 1 && ! isNaN( arguments[0] ) ) {
    this.size = arguments[0];
    this.buf = new Array( arguments[0] );
  }
  else {
    this.size = 0;
    this.buf = new Array();
    for( var i = 0; i < arguments.length; i ++ ) {
      this.buf[this.buf.length] = arguments[i];
    }
  }
  this.length = this.buf.length;
  
  function _concat( param ) {
    for( var i = 0; i < param.length; i ++ ) this.buf[this.buf.length] = param[i];
    this.length = this.buf.length;
  }
  
  function _join( sep ) {
    return this.buf.join( sep );
  }
  
  function _pop() {
    var na;
    na = this.size ? new Array( this.size ) : new Array();
    for( var i = 0; i < this.buf.length - 1; i ++ ) na[na.length] = this.buf[i];
    this.buf = na;
    this.length = this.buf.length;
  }
  
  function _push() {
    for( var i = 0; i < arguments.length; i ++ ) this.buf[this.buf.length] = arguments[i];
    this.length = this.buf.length;
  }
  
  function _reverse() {
    var na;
    na = this.size ? new Array( this.size ) : new Array();
    for( var i = this.buf.length - 1; i >= 0; i -- ) na[na.length] = this.buf[i];
    this.buf = na;
  }
  
  function _shift() {
    var na;
    na = this.size ? new Array( this.size ) : new Array();
    for( var i = 1; i < this.buf.length; i ++ ) na[na.length] = this.buf[i];
    this.buf = na;
    this.length = this.buf.length;
  }
  
  function _slice( from, to ) {
    var na = new Vector();
    if( from >= this.buf.length ) return na;
    if( from < 0 ) from = 0;
    if( to < 0 ) to += this.buf.length;
    else if( to >= this.buf.length ) to = this.buf.length - 1;
    for( var i = from; i <= to; i ++ ) na.buf[na.buf.length] = this.buf[i];
    na.length = na.buf.length;
    return na;
  }
  
  function _splice() {
    if( arguments.length < 2 || isNaN( arguments[0] ) || isNaN( arguments[1] ) ) return;
    var index, count, na, i;
    index = parseInt( arguments[0] );
    if( index < 0 ) index = this.buf.length;
    count = parseInt( arguments[1] );
    if( count <= 0 ) return;
    na = this.size ? new Array( this.size ) : new Array();
    for( i = 0; i < index; i ++ ) na[na.length] = this.buf[i];
    for( var i = 0; i < count; i ++ ) {
      if( arguments.length > i + 2 ) {
        na[na.length] = arguments[i+2];
      }
      else {
        na[na.length] = '';
      }
    }
    for( i = index; i < this.buf.length; i ++ ) na[na.length] = this.buf[i];
    this.buf = na;
    this.length = this.buf.length;
  }
  
  function _sort() {
    return this.buf.sort();
  }
  
  function _unshift() {
    if( ! arguments.length ) return;
    var na, i;
    na = this.size ? new Array( this.size ) : new Array();
    for( i = 0; i < arguments.length; i ++ ) na[na.length] = arguments[i];
    for( i = 0; i < this.buf.length; i ++ ) na[na.length] = this.buf[i];
    this.buf = na;
    return ( this.length = this.buf.length );
  }
  
  function _get( index ) {
    return this.buf[index];
  }
  
}

