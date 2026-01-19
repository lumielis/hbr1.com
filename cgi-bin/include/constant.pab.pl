use PAB::Constant qw(S_USER);

use constant {
	UC_BASE					=> 1,
	UCE_BASE				=> 2,
	UCM_BASE				=> 3,
};

our $UC_INTERNALERROR 		= S_USER + ( UC_BASE << 16 ) + 1;
our $UC_FORMERROR			= S_USER + ( UC_BASE << 16 ) + 2;

our $UC_GB_SUB_ERR_FMT		= S_USER + ( UC_BASE << 16 ) + 100;
our $UC_GB_SUB_ERR_REQ		= S_USER + ( UC_BASE << 16 ) + 101;
our $UC_GB_EML_ERR_FMT		= S_USER + ( UC_BASE << 16 ) + 102;
our $UC_GB_EML_ERR_REQ		= S_USER + ( UC_BASE << 16 ) + 103;
our $UC_GB_MSG_ERR_REQ		= S_USER + ( UC_BASE << 16 ) + 104;
our $UC_GB_POSTING_COMPLETE	= S_USER + ( UC_BASE << 16 ) + 105;


1;

__END__
