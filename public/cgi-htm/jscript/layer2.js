// --------------------------------------------------------------------
// Layer v2.0 - DOM Layer Handler
//
// © 2001-2002 by Christian Müller / spacepille@hotmail.com
//
// License Agreement
// 
// Except as specifically authorized from me, you may not make copies
// of Software, other than a single copy of Software for archival
// purposes. Unless enforcement is prohibited by applicable law, you
// may not modify or publish this Software. 
// --------------------------------------------------------------------

function Layer( id ) {

  this.setPosition = _setPosition;
  this.setDimensions = _setDimensions;
  this.setContent = _setContent;
  this.show = _show;
  this.setZIndex = _setZIndex;
  this.getLeft = _getLeft;
  this.getTop = _getTop;

  this.id = id;
  if( ! this.object ) this.object = ( document.getElementById ? document.getElementById( this.id ) : null );
  
  function _setPosition( x, y ) {
    if( ! this.object ) this.object = ( document.getElementById ? document.getElementById( this.id ) : null );
    if( ! this.object ) return false;
    if( this.object.style ) {
      this.object.style.left = this.left = x;
      this.object.style.top = this.top = y;
    }
  }
  
  function _setDimensions( width, height ) {
    if( ! this.object ) this.object = ( document.getElementById ? document.getElementById( this.id ) : null );
    if( ! this.object ) return false;
    if( this.object.style ) {
      this.object.style.width = this.width = width;
      this.object.style.height = this.height = height;
    }
  }
  
  function _setContent( content ) {
    if( ! this.object ) this.object = ( document.getElementById ? document.getElementById( this.id ) : null );
    if( ! this.object ) return false;
    this.object.innerHTML = content;
  }
  
  function _show( sw_show ) {
    if( ! this.object ) this.object = ( document.getElementById ? document.getElementById( this.id ) : null );
    if( ! this.object ) return false;
    if( this.object.style ) {
      this.object.style.visibility = sw_show ? 'visible' : 'hidden';
    }
  }
  
  function _setZIndex( index ) {
    if( ! this.object ) this.object = ( document.getElementById ? document.getElementById( this.id ) : null );
    if( ! this.object ) return false;
    if( this.object.style ) {
      this.object.style.zIndex = index;
    }
  }
  
  function _getLeft() {
    if( ! this.object ) this.object = ( document.getElementById ? document.getElementById( this.id ) : null );
    if( ! this.object ) return false;
    if( this.object.style )
      return this.object.style.left;
    return -1;
  }
  
  function _getTop() {
    if( ! this.object ) this.object = ( document.getElementById ? document.getElementById( this.id ) : null );
    if( ! this.object ) return false;
    if( this.object.style )
      return this.object.style.top;
    return -1;
  }
  
}
