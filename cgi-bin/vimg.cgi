#!/opt/perl/bin/perl

$tmp = $ENV{'QUERY_STRING'};
$tmp =~ s/.*\/(.*\..*)/$1/g;
$tmp =~ s/\d+\_(.+)/$1/;

$VERSION = '1.0.3';

print "Content-Type: text/html \n\n";

print <<EOFH;
<html>
<head>
<style type="text/css"> <!--
body { scrollbar-base-color:#000000; scrollbar-track-color:#000000; scrollbar-face-color:#000000; scrollbar-highlight-color:#000040; scrollbar-3d-light-color:#000040; scrollbar-dark-shadow-color:#000000; scrollbar-shadow-color:#000000; scrollbar-arrow-color:#000040 }
td, th { font-family: Verdana, Arial, Helvetica, sans-serif, Mono; font-size: 8pt }
a:link {color: #5588FF; text-decoration: none;}
a:visited {color: #5588FF; text-decoration: none;}
a:hover {color: #FF0055; text-decoration: none;}
--> </style>
<title>$tmp - IView v$VERSION - (c) HBR1 2003</title>
<script language="JavaScript"> <!--
document.hl = history.length;
function GoBack() {
  if( document.hl ) history.go( - ( history.length - document.hl + 1 ) );
  else window.close();
}
//--> </script>
</head>
<body bgcolor="#000000" text="#808080" link="#FFFFFF" alink="#FF0000" vlink="#000000">

<table border="0" width="100%" height="100%" cellspacing="0" cellpadding="0">
  <tr>
    <td align="center" valign="middle">
      <table border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td bgcolor="#0055ff" valign="middle">
            <table border="0" cellspacing="1" cellpadding="2">
              <tr>
                <th bgcolor="#003399" height="1%"><font face="Verdana" color="#CCddff">&nbsp;$tmp - IView v$VERSION - (c) HBR1 2003&nbsp;</font></th>
              </tr>
              <tr>
                <th bgcolor="#000000">
                  <table border="0" cellspacing="0" cellpadding="10">
                    <tr>
                      <th><a href="javascript:GoBack();"><img src="$ENV{'QUERY_STRING'}" alt="$tmp" border="0"></a></th>
                    </tr>
                  </table>
                </th>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

</body>
</html>
EOFH
