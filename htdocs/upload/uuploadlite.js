// U-Upload Lite script file.  Simply include this file as a script in your own personal page.
// <script language="javascript" src="uuploadlite.js"></script> is the only code you will need
// to add to your page.  Just make sure that uuploadlite.js is in the same directory as you page.

// Use these Javascript variables to control the applet parameters

var numkeys 			= 1; // The number of keys you have
var keys= new Array(numkeys);

// Enter you keys here in the following form, starting with key[0], key[1], key[2], etc...
 
keys[0]				= ""; 

// Un-comment the folowing lines to add additional keys

//keys[1]			= ""; 
//keys[2]			= "";

// Use these Javascript variables to control the applet parameters


// Connection related values

var server 		= "217.64.169.188";
var port 			= "21";
var pass 			= "anonymous@mail.com";
var user 			= "anonymous";
var passive 	= "true"; 

// Functionallity related values

var remotedir 			= "/incoming";
var localdir 			= "";
var showdialogs 		= "true";

// values that effect the color and interface layout of the client

var width			  = "230";
var height		  = "100";
var bgcolor 		= "50,50,100";		
var textwidth 	= "";
var language 		= "";

// some customizable error pages

var errNavWin 	= "errNavWin.html";
var errIEWin 	= "errIEWin.html";
var errIEWinVM	= "errIEWinVM.html";
var errNavUnix	= "errNavUnix.html";
var errIEMac	= "errIEMac.html";
var errNavMac	= "errNavMac.html";

//*************************************************************************************** //
// ********** DO NOT EDIT BELOW THIS POINT UNLESS YOU KNOW WHAT YOU ARE DOING!  ********* //
//*************************************************************************************** //

	var n;

        var agt=navigator.userAgent.toLowerCase();

        // detect browser version
        // Note: On IE5, these return 4, so use is_ie5up to detect IE5.
        var is_major = parseInt(navigator.appVersion);
        var is_minor = parseFloat(navigator.appVersion);

        // *** BROWSER TYPE ***
        var is_nav  = ((agt.indexOf('mozilla')!=-1) && (agt.indexOf('spoofer')==-1)
                    && (agt.indexOf('compatible') == -1) && (agt.indexOf('opera')==-1)
                    && (agt.indexOf('webtv')==-1));

	var is_opera = (agt.indexOf('opera')!=-1);

        var is_nav4up = (is_nav && (is_major >= 4));
        var is_ie   = (agt.indexOf("msie") != -1);
        var is_ie3  = (is_ie && (is_major < 4));
        var is_ie4  = (is_ie && (is_major == 4) && (agt.indexOf("msie 5.0")==-1) );
        var is_ie5  = (is_ie && (is_major == 4) && (agt.indexOf("msie 5.0")!=-1) );
        var is_ie5up  = (is_ie  && !is_ie3 && !is_ie4);

	// *** PLATFORM ***
        var is_win   = ( (agt.indexOf("win")!=-1) || (agt.indexOf("16bit")!=-1) );
        var is_mac    = (agt.indexOf("mac")!=-1);
        var is_sun   = (agt.indexOf("sunos")!=-1);
        var is_irix  = (agt.indexOf("irix") !=-1);    
        var is_hpux  = (agt.indexOf("hp-ux")!=-1);
        var is_aix   = (agt.indexOf("aix") !=-1);     
        var is_linux = (agt.indexOf("inux")!=-1);
        var is_sco   = (agt.indexOf("sco")!=-1) || (agt.indexOf("unix_sv")!=-1);
        var is_unixware = (agt.indexOf("unix_system_v")!=-1);
        var is_mpras    = (agt.indexOf("ncr")!=-1);
        var is_reliant  = (agt.indexOf("reliantunix")!=-1);
        var is_dec   = ((agt.indexOf("dec")!=-1) || (agt.indexOf("osf1")!=-1) ||
               (agt.indexOf("dec_alpha")!=-1) || (agt.indexOf("alphaserver")!=-1) ||
               (agt.indexOf("ultrix")!=-1) || (agt.indexOf("alphastation")!=-1));
        var is_sinix = (agt.indexOf("sinix")!=-1);
        var is_freebsd = (agt.indexOf("freebsd")!=-1);
        var is_bsd = (agt.indexOf("bsd")!=-1);
        var is_unix  = ((agt.indexOf("x11")!=-1) || is_sun || is_irix || is_hpux ||
                     is_sco ||is_unixware || is_mpras || is_reliant ||
                     is_dec || is_sinix || is_aix || is_linux || is_bsd || is_freebsd);

	function appletWillRun() {

        	if (is_ie4 || is_ie5up || (is_nav4up && is_minor >= 4.08))                     
			return true;           
        	return false;
	}

	

	function mrj()  {
 		for (var i = 0; i < navigator.plugins.length; i++) {
  			if (navigator.plugins[i].name.indexOf("MRJ") > -1) return true;
 		} 
		return false;
	}

	// The following script will detect weather MAC or PC version of the applet should be started 
	// and set up the apropriate jar file

	var jar = "uuploadlite.jar";
	if (is_mac) {
		var jar = "uuploadliteMac.jar";
	}

	var netmac=0;			// netmac = 0 means it is either Mac/IE or non-Mac
	if (is_mac && is_nav) {		
 		if (mrj()) {
 			var netmac=1;	// netmac = 1 means this is Mac/Netscape so use <embed> tag
			
		}
		else { 
			var netmac=2;	// this is Mac/Netscape w/o MRJ Plugin
		}	
	}

	if (netmac == 0) {
  	    if (appletWillRun()) {
  		document.write("<APPLET name=uuploadlite code=unlimited.ftp.UUploadLite.class height="+height+" width="+width+" archive=\""+jar+"\" MAYSCRIPT VIEWASTEXT>");
  		document.write("<PARAM NAME='cabbase' VALUE='uuploadlite.cab'>");
  		document.write("<PARAM NAME='bgcolor' VALUE='"+bgcolor+"'>");
  		document.write("<PARAM NAME='server' VALUE='"+server+"'>");
  		document.write("<PARAM NAME=\"port\" VALUE=\""+port+"\">");
  		document.write("<PARAM NAME=\"pass\" VALUE=\""+pass+"\">");
  		document.write("<PARAM NAME=\"user\" VALUE=\""+user+"\">");
		document.write("<PARAM NAME='passive' VALUE='"+passive+"'>");
  		document.write("<PARAM NAME=\"remotedir\" VALUE=\""+remotedir+"\">");
  		document.write("<PARAM NAME=\"localdir\" VALUE=\""+localdir+"\">");
  		document.write("<PARAM NAME=\"textwidth\" VALUE=\""+textwidth+"\">");
		document.write("<PARAM NAME=\"showdialogs\" VALUE=\""+showdialogs+"\">");
  		document.write("<PARAM NAME=\"language\" VALUE=\""+language+"\">");
		document.write("<PARAM NAME='errIEWinVM' VALUE='"+errIEWinVM+"'>");
		for (n=0; n < numkeys; n++)
			document.write("<PARAM NAME='key"+(n+1)+"' VALUE='"+keys[n]+"'>");			
  		document.write("<h1>");
  		document.write("Error!!! Java is disabled.</h1>");
  		document.write("Please enable Java and reload this page.");
  		document.write("</APPLET>");	
	    } else if (!appletWillRun()) {
		if (is_nav && is_win)  {
			window.location.href=errNavWin;			
		}
		else if (is_ie && is_win) {
			window.location.href=errIEWin;		
		}
		else if (is_nav && is_unix) {
			window.location.href=errNavUnix;
		}
		else if (is_ie && is_mac) {
			window.location.href=errIEMac;
		}
		else if (is_opera) {
			alert("Opera browser not supported at this time");
		}
					
	   }
	}
	else if (netmac==1){ //It's Netscape use the embed tag instead!
	    document.writeln("<EMBED  TYPE = \"application/x-java-vm\" name=\"uuploadlite\" PLUGINSPAGE = \"http://www.mozilla.org/oji/\" BORDER=\"0\"");
		document.writeln("code=\"unlimited.ftp.UUploadLite.class\" height="+height+" width="+width+"  archive=\""+jar+"\" "); 
  		document.writeln("bgcolor=\""+bgcolor+"\""); 
		document.writeln("server=\""+server+"\"");
		document.writeln("port=\""+port+"\""); 
		document.writeln("pass=\""+pass+"\""); 
		document.writeln("passive='"+passive+"'");		
		document.writeln("user=\""+user+"\""); 
		document.writeln("remotedir=\""+remotedir+"\"");
		document.writeln("localdir=\""+localdir+"\"");
		document.writeln("textwidth=\""+textwidth+"\""); 
		document.writeln("showdialogs=\""+showdialogs+"\""); 	
		document.writeln("language=\""+language+"\""); 	
		document.writeln("errIEWinVM='"+errIEWinVM+"'");
		for (n=0; n < numkeys; n++)
			document.write("key"+(n+1)+"='"+keys[n]+"'");	
		document.writeln(">"); 
	}
	else if (netmac==2) {//Netscape doesn't have the MRJ Plugin.
		window.location.href=errNavMac;
	}


