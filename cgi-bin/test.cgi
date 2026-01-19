#!/usr/bin/perl

print "Content-type: text/html \n\n";

print "<table>";
foreach( keys %ENV ) {
  print '<tr><td>' . $_ . '</td><td>' . $ENV{$_} . '</td></tr>';
}
print '</table>';
