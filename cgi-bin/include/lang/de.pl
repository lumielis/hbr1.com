require $ENV{'SCRIPT_PATH'} . './include/constant.pab.pl';

*RC = \%PAB::System::Lang::DE::RESOURCE_STRING;

$RC{$UC_INTERNALERROR} = qq{
An internal error occured, while processing data. Please try again later,
 or contact <a href="mailto:pille\@hbr1.com">pille</a>.
};

$RC{$UC_FORMERROR} = qq{
There are errors. Please check the fields with red asterix.
};

### GUESTBOOK ###

$RC{$UC_GB_SUB_ERR_FMT} = q{
Erlaubte Zeichen im <b>Name</b> sind Buchstaben, Zahlen und Leerzeichen.
};
$RC{$UC_GB_SUB_ERR_REQ} = q{
Bitte gib <b>Deinen Namen</b> an.
};

$RC{$UC_GB_EML_ERR_FMT} = q{
Bitte stelle sicher, daß die <b>E-Mail Adresse</b> in diesem Format angegeben
 ist. (z.B meinname@firma.com)
};
$RC{$UC_GB_EML_ERR_REQ} = q{
Bitte gib eine <b>E-Mail Adresse</b> an.
};

$RC{$UC_GB_MSG_ERR_REQ} = q{
Bitte gib eine <b>Mitteilung</b> ein.
};

$RC{$UC_GB_POSTING_COMPLETE} = q{
Vielen Dank für Deinen Eintrag!
};

1;

__END__
