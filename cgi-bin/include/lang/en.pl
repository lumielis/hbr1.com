&PAB::System::requireAndRun( $ENV{'SCRIPT_PATH'} . './include/constant.pab.pl' );

*RC = \%PAB::System::Lang::EN::RESOURCE_STRING;

$RC{$UC_INTERNALERROR} = qq{
An internal error occured, while processing data. Please try again later,
 or contact <a href="mailto:pille\@hbr1.com">pille</a>.
};

$RC{$UC_FORMERROR} = qq{
There are errors. Please check the fields with red asterix.
};

### GUESTBOOK ###

$RC{$UC_GB_SUB_ERR_FMT} = q{
Your <b>Name</b> contains Invalid characters.
 Only <b>letters, numbers, spaces, - and _</b> are allowed in this field.
};
$RC{$UC_GB_SUB_ERR_REQ} = q{
Please specify <b>Your Name</b>.
};

$RC{$UC_GB_EML_ERR_FMT} = q{
Please make sure your <b>Email Address</b> is entered in
 this format. (eg. myname@company.com).
};
$RC{$UC_GB_EML_ERR_REQ} = q{
Please specify an <b>Email Address</b>.
};

$RC{$UC_GB_MSG_ERR_REQ} = q{
Please enter a <b>Comment</b>.
};

$RC{$UC_GB_POSTING_COMPLETE} = q{
Thank you!
};

1;

__END__
