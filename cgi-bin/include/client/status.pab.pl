use GTop ();

my $proc_mem = GTop->new->proc_mem( $Form{'pid'} || $$ );

print "<pre>\n";

print "Total memory of the process: " .
	&formatNumber( $proc_mem->size ), "\n";

print "Shared memory of the process: " .
	&formatNumber( $proc_mem->share ), "\n";

print "Resident memory of the process: " .
	&formatNumber( $proc_mem->resident ), "\n";

print "Process ID: $$, Parent PID: ", getppid(), "\n";

print "</pre>\n";

1;
