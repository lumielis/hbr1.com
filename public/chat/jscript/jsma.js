// --------------------------------------------------------------------
// JSM Chat Engine 1.5a - Client Side
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
 
// misc functions

function ConnectUrl( url, handler ) {
  if( this.IE_Download ) { // microsoft
    IE_Download.startDownload( url, handler );
    return true;
  }
  else if( this.JavaDownload ) { // java
    if( handler ) handler( JavaDownload( url ) );
    return true;
  }
  else if( this.Packages ) { // mozilla
    //alert( document.applets.NSDownload );
    var url = new Packages.java.net.URL( url );
    var con = url.openConnection();
    con.setUseCaches( false );
    var din = new Packages.java.io.DataInputStream( con.getInputStream() );
    var out = '';
    while( din.available() ) out += din.readLine();
    din.close();
    if( handler ) handler( out );
    return true;
  }
  else {
    alert( "Your Browser doesn't support this JavaScript Function" );
    return false;
  }
}

function GetTime( sec ) {
  var now, res;
  now = new Date();
  if( ! isNaN( sec ) )
    now.setTime( sec * 1000 + now.getTimezoneOffset() * 60000 );
  res = ( now.getHours() < 10 ? '0' : '' ) + now.getHours();
  res += ':' + ( now.getMinutes() < 10 ? '0' : '' ) + now.getMinutes();
  res += ':' + ( now.getSeconds() < 10 ? '0' : '' ) + now.getSeconds();
  return res;
}

function ChatMessage() {
  if( ! arguments.length ) return '';
  var pos, regexp, out;
  for( pos = 0; pos < JSM_Messages.length; pos += 2 ) {
    if( JSM_Messages[pos] == arguments[0] ) {
      out = JSM_Messages[pos+1];
      break;
    }
  }
  if( pos == JSM_Messages.length ) return '';
  pos = 1;
  while( pos < arguments.length ) {
    regexp = new RegExp( '%p' + pos, 'g' );
    out = out.replace( regexp, arguments[pos] );
    pos ++;
  }
  return out;
}

// chat functions

function requestUrl( url, wait ) {
  if( wait || DataBusy ) {
    MsgBuffer.push( url );
    return true;
  }
  DataBusy = 1;
  return GetUrl( url );
}

function GetUrl( url ) {
  if( useFrame && frames['data'] ) {
    frames['data'].location.href = url;
    return true;
  }
  return ConnectUrl( url, OnDownloadDone );
}

function GetData() {
  if( ! DataBusy ) {
    DataSuspend = 0;
    DataBusy = 1;
    if( MsgBuffer.length ) {
      GetUrl( MsgBuffer.get( 0 ) );
      MsgBuffer.shift();
    }
    else {
      GetUrl( JSM_Url + 'jsma.cgi?' + useFrame + '|' + JSM_Id + "|" + JSM_RoomId );
    }
  }
  else {
    WaitForData();
  }
}

function WaitForData() {
  if( DataTimer ) {
    clearTimeout( DataTimer );
    DataTimer = 0;
  }
  if( DataSuspend > DataTimeout ) {
    if( confirm( ChatMessage( 'TIMEOUT' ) ) ) {
      Logout();
      location.reload();
      return;
    }
    DataSuspend = 0;
  }
  else {
    DataSuspend += Refresh;
  }
  if( ClientActive ) DataTimer = setTimeout( "GetData()", Refresh );
}

function OnDownloadDone( content ) {
  var da, i, j, ua, out;
  DataBusy = 0;
  if( content != '0' ) {
    da = content.split( "|" );
    for( i = 0; i < da.length; i += 2 ) {
      if( da[i] )
      switch( parseInt( da[i] ) ) {
        case 1: // message
          if( da[i+1] ) AddLine( unescape( da[i+1] ) );
          break;
        case 2: // logged in
          ClientActive = 1;
          JSM_Id = da[i+1];
          if( JSM_Cookie ) {
            SetCookie( 'JSM_Client', JSM_Client );
            SetCookie( 'JSM_Pass', JSM_Pass );
          }
          SetCookie( 'JSM_Room', JSM_Room );
          //GetSessionTime();
          if( this.FocusInput ) FocusInput();
          //if( this.OnRoomImage ) OnRoomImage(  JSM_Url + '/jsmg.cgi?' + escape( JSM_Room ) + '.jpg' );
          if( this.OnLogin ) OnLogin();
          break;
        case 3: // logged out
          ClientActive = 0;
          UpdateUserList( "" );
          AddLine( ChatMessage( 'LEAVE_ROOM', SysColor, JSM_Room ) );
          if( this.OnLogout ) OnLogout();
          break;
        case 4: // room changed
          ua = da[i+1].split( "\xFF" );
          JSM_RoomId = ua[0];
          JSM_Room = unescape( ua[1] );
          SetCookie( 'JSM_Room', JSM_Room );
          AddLine( ChatMessage( 'ENTER_ROOM', SysColor, JSM_Room ) );
          GetSessionTime();
          if( this.FocusInput ) FocusInput();
          //if( this.OnRoomImage ) OnRoomImage( JSM_Url + '/jsmg.cgi?' + ua[1] + '.jpg' );
          break;
        case 5: // can't login
          UpdateUserList( "" );
          break;
        case 6: // display userlist
          ua = da[i+1].split( "\xFF" );
          out = "";
          for( j = 0; j < ua.length; j += 3 ) {
            if( ua[j+1] ) out += '<b>' + ua[j+1] + '</b> (' + ua[j+2] +  '), ';
          }
          AddLine( ChatMessage( 'ALL_USERS', SysColor, JSM_Room, unescape( out.substr( 0, out.length - 2 ) ) ) );
          break;
        case 7: // update userlist
          UpdateUserList( da[i+1] );
          break;
        case 8: // display roomlist
          ua = da[i+1].split( "\xFF" );
          out = "";
          for( j = 0; j < ua.length; j += 3 ) {
            if( ua[j+1] ) out += ( ua[j+2] == 2 ? '*' : '' ) + ua[j+1] + ", ";
          }
          AddLine( ChatMessage( 'ALL_ROOMS', SysColor, unescape( out.substr( 0, out.length - 2 ) ) ) );
          break;
        case 9: // update roomlist
          UpdateRoomList( da[i+1] );
          break;
        case 10: // session time
          ua = da[i+1].split( "\xFF" );
          if( this.OnSessionTime ) OnSessionTime( ua[0] * 1000, ua[1] * 1000 );
          if( ua[2] ) AddLine( ChatMessage( 'SESSIONTIME', SysColor ) );
          if( this.OnRoomImage ) {
            if( ua[0] == 0 )
              OnRoomImage(  JSM_ImageEmpty );
            else {
              if( this.OnInitServer ) OnInitServer( JSM_Protocol, JSM_Host, JSM_Port );
              if( this.OnSetRefresh ) OnSetRefresh( JSM_Refresh );
              if( JSM_Protocol == 'NETFILE' )
                OnRoomImage( escape( JSM_Room ) + '.jpg', JSM_Owner );
              else
                OnRoomImage( JSM_Image + escape( escape( JSM_Room ) ) + '.jpg', JSM_Owner );
            }
          }
          break;
        case 99: // errors / warnings
          switch( parseInt( da[i+1] ) ) {
          case 1: // unknown client
            AddLine( ChatMessage( 'UNKNOWN_CLIENT' ) );
            break;
          case 2: // unknown password
            AddLine( ChatMessage( 'UNKNOWN_PASSWORD' ) );
            break;
          case 3: // must login
            AddLine( ChatMessage( 'MUST_LOGIN' ) );
            break;
          case 4: // guest account
            AddLine( ChatMessage( 'GUEST', SysColor ) );
            break;
          default: // ?????
            AddLine( ChatMessage( 'UNKNOWN', da[i+1] ) );
            break;
          }
          break;
        default: // ???
          alert( i + ': ' + da[i] + " - " + da[i+1] + "\n" + content );
      }
    }
    if( Changed ) DrawData();
  }
  WaitForData();
}

// frame edition
function OnData( Sender ) {
  if( ! Sender || ! Sender.document || ! Sender.document.forms || ! Sender.document.forms[0].Data  ) return false;
  OnDownloadDone( Sender.document.forms[0].Data.value );
  //if( this.JavaDownload ) useFrame = 0;
}

function OnResize() {
  if( document.getElementById ) return; // DOM does NOT need to reload
  DrawData();
  SendMessage( "" );
  Resized = 1;
  for( var i = 0; i < Frames.length; i ++ ) {
    if( this.frames[Frames[i]] ) this.frames[Frames[i]].location.reload();
  }
}

function DrawData( child ) {
  if( ! this.main || ! this.main.document ) return false;
  main.document.open( "text/html", "replace" );
  var out = '<head><style type="text/css"> <!--\n';
  if( ! JSM_Scrollbar ) JSM_Scrollbar = 'scrollbar-base-color:#E7E3E7';
  out += 'body { font-family: Verdana; font-size: 12px }\n';
  out += 'td, th { font-family: Verdana; font-size: 12px }\n';
  out += '#xs { font-size: 10px }\n';
  out += '--> </style></head>\n';
  out += '<body bgcolor=#' + BgColor + ' text=#' + FgColor + ' style="scrollbar-base-color:#E7E3E7">\n';
  if( DrawReverse ) {
    out += '<a name="e_chat" id="e_chat" />\n';
    for( var i = ChatLines.length - 1; i >= 0; i -- ) {
      out += ChatLines.get( i ) + "<br>\n";
    }
    out += '<a name="b_chat" id="b_chat" />\n';
  }
  else {
    out += '<a name="b_chat" id="b_chat" />\n';
    for( var i = 0; i < ChatLines.length; i ++ ) {
      out += ChatLines.get( i ) + "<br>\n";
    }
    out += '<a name="e_chat" id="e_chat" />\n';
  }
  main.document.write( out + '</body>\n' );
  main.document.close();
  if( ! DrawReverse ) {
    if( main.document.all ) {
      main.document.all.e_chat.scrollIntoView();
    }
    else if( main.scrollTo ) {
      main.scrollTo( 0, 30000 );
    }
    else {
      DrawReverse = 1;
      return DrawData();
    }
  }
  Changed = 0;
  return true;
}

function AddLine( msg ) {
  if( ! ChatLines ) return false;
  while( ChatLines.length > LinesMax ) ChatLines.shift();
  ChatLines.push( msg );
  Changed = 1;
  return true;
}

function SendMessage( msg, flags ) {
  var params, pos, cmd, action;
  if( ! msg ) return false;
  action = params = '';
  cmd = 0;
  if( msg.charAt( 0 ) == '/' ) {
    if( msg.search( /^\/help/i ) > -1 || msg.search( /^\/\?/i ) > -1 ) {
      msg = '<br><table border=0>';
      for( var i = 0; i < JSM_Help.length; i += 2 ) {
        if( ! JSM_Help[i] )
          msg += '<tr><td colspan="2"><font color=#' + SysColor + '>' + JSM_Help[i+1] + '</font></td></tr>\n';
        else
          msg += '<tr><td>&nbsp;&nbsp;' + JSM_Help[i] + '</td><td><font color=#' + SysColor + '>' + JSM_Help[i+1] + '</font></td></tr>\n';
      }
      msg += '</table>';
      AddLine( msg );
      DrawData();
      return true;
    }
    else if( msg.search( /^\/me( .+)/i ) > -1 ) {
      msg = escape( "<font color=#" + JSM_Color + "><i>" + JSM_Client + RegExp.$1 + "</i></font>" );
      cmd = 1;
    }
    else if( msg.search( /^\/screech (.+)/i ) > -1 ) {
      msg = escape( ChatMessage( 'SCREECH', JSM_Color, JSM_Client, RegExp.$1.toUpperCase() ) );
      cmd = 1;
    }
    else if( msg.search( /^\/room (.+)/i ) > -1 ) {
      return ChangeRoom( RegExp.$1 );
    }
    else if( msg.search( /^\/rooms/i ) > -1 ) {
      msg = "";
      action = 3;
      params = JSM_Id + '|' + JSM_RoomId + '|12';
      cmd = 1;
    }
    else if( msg.search( /^\/users/i ) > -1 ) {
      msg = "";
      action = 3;
      params = JSM_Id + '|' + JSM_RoomId + '|3';
      cmd = 1;
    }
    else if( msg.search( /^\/color (.+)/i ) > -1 ) {
      if( ! SetColor( RegExp.$1.toUpperCase() ) ) {
        AddLine( ChatMessage( 'WRONG_COLOR', SysColor ) );
        DrawData();
        return false;
      }
      return true;
    }
    else if( msg.search( /^\/login (.+) (.+)/i ) > -1 ) {
      return Login( RegExp.$1, RegExp.$2 );
    }
    else if( msg.search( /^\/login (.+)/i ) > -1 ) {
      return Login( RegExp.$1 );
    }
    else if( msg.search( /^\/login/i ) > -1 ) {
      return Login();
    }
    else if( msg.search( /^\/logout/i ) > -1 ) {
      return Logout();
    }
    else {
      var Exp = /\/(\S+)\s+(.+)/;
      Exp.exec( msg );
      if( RegExp.$1 && RegExp.$2 ) {
        Exp = GetClientId( RegExp.$1 );
        if( Exp ) return PrivateMessage( Exp, RegExp.$2 );
      }
    }
  }
  if( ! ClientActive ) {
    AddLine( ChatMessage( 'MUST_LOGIN' ) );
    DrawData();
    return true;
  }
  if( ! cmd ) msg = escape( "<font color=#" + JSM_Color + ">" + JSM_Client + ': ' + msg + "</font>" );
  if( ! params ) params = JSM_Id + "|" + JSM_RoomId + "|" + flags;
  if( ! action ) action = 1;
  return requestUrl( JSM_Url + 'jsma.cgi?' + ( action + useFrame ) + '|' + params + '|' + msg );
}

function RawMessage( msg, flags, extra, action ) {
  msg = escape( msg );
  if( isNaN( action ) ) action = 1;
  var params = JSM_Id + "|" + JSM_RoomId + "|" + flags;
  return requestUrl( JSM_Url + 'jsma.cgi?' + ( action + useFrame ) + '|' + params + '|' + msg + '|' + extra );
}

function PrivateMessage( id, msg ) {
  var i;
  if( id == JSM_Id ) {
    AddLine( ChatMessage( 'SELF_MSG', SysColor ) );
    DrawData();
    if( this.FocusInput ) FocusInput();
    return false;
  }
  for( i = 0; i < UserList.length; i += 3 ) {
    if( UserList[i] == id ) {
      if( ! msg ) msg = prompt( ChatMessage( 'PRIVATE_MSG', UserList[i+1] ), '' );
      if( msg ) {
        msg = ChatMessage( 'WHISPER', JSM_Color, JSM_Client, msg );
        if( RawMessage( msg, 1, id, 1 ) ) {
          AddLine( ChatMessage( 'MSG_SENT', SysColor, UserList[i+1] ) );
          DrawData();
        }
        if( this.FocusInput ) FocusInput();
        return true;
      }
    }
  }
  return false;
}

function Logout() {
  if( ! ClientActive ) {
    AddLine( ChatMessage( 'ALLREADY_LOGGEDOUT', SysColor ) );
    DrawData();
    return true;
  }
  var msg = escape( ChatMessage( 'LEFT_ROOM', SysColor, GetTime(), JSM_Client ) );
  var params = escape( JSM_Client ) + '|' + escape( JSM_Pass ) + '|' + JSM_RoomId + '|';
  var action = useFrame + 2;
  return requestUrl( JSM_Url + 'jsma.cgi?' + action + '|' + params + '|' + msg );
}

function Login( user, pass ) {
  if( ClientActive ) {
    AddLine( ChatMessage( 'ALLREADY_LOGGEDIN', SysColor ) );
    DrawData();
    return true;
  }
  if( user ) {
    JSM_Client = user;
    JSM_Pass = pass;
  }
  else if( ! JSM_Client || ! JSM_Pass ) {
    if( ! JSM_Cookie ) return false;
    JSM_Client = GetCookie( 'JSM_Client' );
    JSM_Pass = GetCookie( 'JSM_Pass' );
    if( ! JSM_Client ) return false;
  }
  if( ! JSM_Room ) JSM_Room = GetCookie( 'JSM_Room' );
  if( ! JSM_Room && RoomList.length > 2 ) JSM_Room = RoomList[1];
  var msg = escape( ChatMessage( 'ENTERS_ROOM', SysColor, GetTime(), JSM_Client ) );
  var params = escape( JSM_Client ) + '|' + escape( JSM_Pass ) + '||' + escape( JSM_Room );
  var action = useFrame + 2;
  return requestUrl( JSM_Url + 'jsma.cgi?' + action + '|' + params + '||' + msg );
}

function ChangeRoom( room ) {
  if( ! room || ! ClientActive ) return false;
  if( room == JSM_Room ) {
    AddLine( ChatMessage( 'ALLREADY_INROOM', SysColor ) );
    DrawData();
    return true;
  }
  var params = escape( JSM_Client ) + '|' + escape( JSM_Pass ) + '|' + JSM_RoomId + '|' + escape( room );
  var msg = escape( ChatMessage( 'CHANGE_ROOM', SysColor, GetTime(), JSM_Client, room ) );
  msg += '|' + escape( ChatMessage( 'ENTERS_ROOM', SysColor, GetTime(), JSM_Client ) );
  var action = useFrame + 2;
  return requestUrl( JSM_Url + 'jsma.cgi?' + action + '|' + params + '|' + msg );
}

function GetSessionTime() {
  var action = 3;
  var flags = 16;
  var params = ( action + useFrame ) + '|' + JSM_Id + '|' + JSM_RoomId + '|' + flags;
  return requestUrl( JSM_Url + 'jsma.cgi?' + params );
}

function UpdateUserList( str ) {
  var da, i, f;
  da = str.split( "\xFF" );
  f = ! str;
  if( ! f )
    for( i = 0; i < da.length; i += 3 )
      if( da[i] && da[i] != UserList[i] ) f = 1;
  if( ! f )
    for( i = 0; i < UserList.length; i += 3 )
      if( da[i] != UserList[i] ) f = 1;
  UserList = new Array();
  for( i = 0; i < da.length; i += 3 ) {
    if( da[i] && da[i+1] ) {
      UserList[UserList.length] = da[i];
      UserList[UserList.length] = unescape( da[i+1] );
      UserList[UserList.length] = da[i+2];
    }
  }
  if( ! f ) return;
  if( this.OnUserList ) OnUserList( UserList );
}

function GetClientId( nick ) {
  for( var i = 0; i < UserList.length; i += 3 ) {
    if( UserList[i+1] == nick ) return UserList[i];
  }
  return 0;
}

function UpdateRoomList( str ) {
  var da, i;
  da = str.split( "\xFF" );
  RoomList = new Array();
  for( i = 0; i < da.length; i += 3 ) {
    if( da[i] && da[i+1] && da[i+2] == 1 ) {
      RoomList[RoomList.length] = da[i];
      RoomList[RoomList.length] = unescape( da[i+1] );
    }
  }
  if( ! JSM_Room ) JSM_Room = GetCookie( 'JSM_Room' );
  if( ! JSM_Room && RoomList.length > 2 ) JSM_Room = RoomList[1];
  if( this.OnRoomList ) OnRoomList( RoomList, JSM_Room );
  if( LoginRequest ) {
    LoginRequest = 0;
    if( ! Login() ) UpdateUserList( "" );
  }
}

function VerifyColor( color ) {
  color = color.substr( 0, 6 ).toUpperCase();
  var h = 1, ch;
  for( var i = 0; i < color.length; i ++ ) {
    ch = color.charCodeAt( i );
    if( ch < 48 || ( ch > 57 && (  ch < 65 ||  ch > 70 ) ) ) {
      h = 0;
      break;
    }
  }
  if( color.length < 6 || ! h ) return false;
  return true;
}

function SetColor( color ) {
  if( ! VerifyColor( color ) ) return false;
  JSM_Color = color;        
  AddLine( ChatMessage( 'COLOR_CHANGED', color ) );
  DrawData();
  SetCookie( 'JSM_Color', color );
  if( this.OnSetColor ) OnSetColor( color );
  return true;
}

function SetBgColor( color ) {
  if( ! VerifyColor( color ) ) return false;
  BgColor = color;
  var col = new Color( color );
  var c = parseInt( ( col.R + col.G + col.B ) / 3 );
  c = Math.abs( c <= 128 ? ( c - 128 ) : ( 128 - c ) );
  col.R = col.G = col.B = c;
  SysColor = col.AsString();
}

function SetFgColor( color ) {
  if( ! VerifyColor( color ) ) return false;
  FgColor = color;
}

function SetRefresh( time ) {
  if( this.OnSetRefresh ) OnSetRefresh( time );
}

function AddReloadFrame( frame ) {
  if( ! frame || ! frame.location ) return;
  Frames[Frames.length] = frame.name;
}

// initialization

function InitJSM() {
  AddLine( '<font color=#' + SysColor + ' id="xs"><i><b>' + JSM_Owner + '</b> - JSM Engine 1.5a Beta - ' + ( useFrame ? 'Frame' : this.Packages ? 'Java' : 'Advanced' ) + ' Edition</i></font>' );
  AddLine( ChatMessage( 'WELCOME', SysColor ) );
  DrawData();
  LoginRequest = 1;
  RawMessage( "", 4, "", 3 );
  if( this.OnSetColor ) OnSetColor( JSM_Color );
  window.onresize = OnResize;
}

window.onload = InitJSM;
window.onunload = Logout;

var LinesMax = 100;
var DrawReverse = 0;

var RefreshMin = 500;
var RefreshMax = 5000;
var Refresh = 500;

var Frames = new Array();
var ChatLines = new Vector();
var DataBusy = 0;
var DataTimer = 0;
var DataTimeout = RefreshMax * 2;
var DataSuspend = 0;
var ClientActive = 0;
var MsgBuffer = new Vector();
var UserList = new Array();
var RoomList = new Array();
var RoomId = 0;
var useFrame = ( ! this.IE_Download  ) ? 0xFF0000 : 0;
var Changed = 0;
var LoginRequest = 0;

var JSM_Color = GetCookie( 'JSM_Color' );
if( ! JSM_Color || ! VerifyColor( JSM_Color ) ) {
  var col = new Color();
  col.R = 50 + parseInt( Math.random() * 200 );
  col.G = 50 + parseInt( Math.random() * 200 );
  col.B = 50 + parseInt( Math.random() * 200 );
  JSM_Color = col.AsString();
}

var JSM_Scrollbar = 'scrollbar-base-color:#E7E3E';
var JSM_Url = 'cgi-bin/';
var JSM_Owner = 'unregistered';
var JSM_Cookie = 1;
var JSM_Id = 0;
var JSM_Client = '';
var JSM_Pass = '';
var JSM_RoomId = 0;
var JSM_Room = '';

var JSM_Help = new Array
  ( 0, '<b>JSM command help</b>'
  , '/color rrggbb', 'make you colorful'
  , '/screech msg', 'be loud :-)'
  , '/help', 'same like /?'
  , '/login [nick] [pass]', 'signin to chat'
  , '/logout', 'go out and save resources'
  , '/me msg', 'ego perspective'
  , '/name msg', 'be personal to somebody'
  , '/room name', 'go in another room'
  , '/rooms', 'view all rooms in space'
  , '/users', 'view all fraggles staying here'
  , 0, '&nbsp;'
  , 0, '<b>input helpers*</b>'
  , 'Arrow Up', 'select previous comment'
  , 'Arrow Down', 'select next comment'
  , 0, '&nbsp;<span id="xs">*not available everywhere :-(</span>'
  );

var JSM_Messages = new Array(
  'ENTER_ROOM', '<i><font color=#%p1>you enter the room </font>%p2</i>',
  'LEAVE_ROOM', '<i><font color=#%p1>you left the room </font>%p2</i>',
  'ALL_ROOMS', '<i><font color=#%p1>all rooms in space: </font>%p2</i>',
  'ALL_USERS', '<i><font color=#%p1>all fraggles in %p2: </font>%p3</i>',
  'UNKNOWN_CLIENT', '<i><font color=#FF0000>access denied</font></i>',
  'UNKNOWN_PASSWORD', '<i><font color=#FF0000>access denied</font></i>',
  'MUST_LOGIN', '<i><font color=#FF0000>you should signin first</i></font>',
  'GUEST', '<i><b><font color=#%p1>hello stranger, you are welcome</b></i></font>',
  'UNKNOWN', '<i><font color=#FF0000>unknown sequence: %p1</i></font>',
  'SCREECH', '<font color=#%p1><i><b>%p2 screeches: %p3</b></i></font>',
  'WHISPER', '<font color=#%p1><i><b>%p2 whispers: %p3</b></i></font>',
  'WRONG_COLOR', '<font color=#%p1><i>colors has to written as hex byte code in order rgb</i></font>',
  'SELF_MSG', '<font color=#%p1><i>please be careful</i></font>',
  'PRIVATE_MSG', 'private conversation with %p1',
  'MSG_SENT', '<i><b><font color=#%p1>data has been send to %p2</b></i></font>',
  'ALLREADY_LOGGEDIN', '<i><font color=#%p1>you are allready signed in</i></font>',
  'ALLREADY_LOGGEDOUT', '<i><font color=#%p1>you are allready signed out</i></font>',
  'ALLREADY_INROOM', '<i><font color=#%p1>you are allready here</i></font>',
  'LEFT_ROOM', '<i>%p2 - %p3<font color=#%p1> left us</font></i>',
  'ENTERS_ROOM', '<i>%p2 - %p3<font color=#%p1> want to meet us</i></font>',
  'CHANGE_ROOM', '<i>%p2 - %p3<font color=#%p1> flies to room </font>%p4</i>',
  'COLOR_CHANGED', '<font color=#%p1><i>you changed your color</i></font>',
  'WELCOME', '<i><font color=#%p1 id="xs">Hint: enter /? to get a description of all commands</font></i><br>',
  'SESSIONTIME', '<i><font color=#%p1>interview update</font></i>',
  'TIMEOUT', 'the server does not response\nshould i restart the system?'
);