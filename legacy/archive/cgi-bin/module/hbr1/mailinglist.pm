package hbr1::mailinglist;

# =============================================================================
# Web Application Environment - Simply Site Management System
# Module: Mailinglist
# =============================================================================
# Idea, Design and Development by Christian Mueller
# Available at hollowtan@hotmail.com
# =============================================================================
# License Agreement
# -----------------
# Except as specifically authorized from me, you may not make copies
# of Software, other than a single copy of Software for archival
# purposes. Unless enforcement is prohibited by applicable law, you
# may not modify or publish this Software. 
# =============================================================================
# Steps Of Evolution
# ------------------
# e1.0.0
# - my birth
# =============================================================================

### loading required files ####

require $main::path{'ssms'} . 'perl.pl';

require ssms::module::access;
require ssms::module::access::table;

### defining static variables ####

our @ISA = qq(ssms::module::access::table);

our $VERSION = qq(1.0.0);

our %setup_default = (
	# default table name
	table => 'mailinglist',
	# first run
	first_run => 1,
	# my access model
	access => {
		modus => 0640,
		owner => 0,
		group => 0,
	}
);

our $setup_sql = qq(
	CREATE TABLE `<MY_TABLE_NAME>` (
	  `id` bigint(20) unsigned NOT NULL auto_increment,
	  `name` varchar(255) NOT NULL default '',
	  `email` varchar(255) NOT NULL default '',
	  `timestamp_create` timestamp(14) NOT NULL,
	  `timestamp_modify` timestamp(14) NOT NULL,
	  `verify` varchar(12) NOT NULL default '',
	  `status` tinyint(3) unsigned NOT NULL default '1',
	  `user_id` int(10) unsigned NOT NULL default '0',
	  `group_id` int(10) unsigned NOT NULL default '0',
	  `access` int(10) unsigned NOT NULL default '420',
	  PRIMARY KEY  (`id`)
	) TYPE=MyISAM
);

### public functions ###

sub new {
	my $proto = shift;
	my $class = ref( $proto ) || $proto;
	my $access = shift;
	if( ! defined $access || $access !~ /^ssms::module::access=(HASH.+)/ ) {
		die 'Error 500: No access manager installed for ' . $class;
	}
	my $self  = {};
	bless( $self, $class );
	${ $self->{'module'} }{'access'} = $access;
	%{ $self->{'setup'} } = %setup_default;
	&ssms::perl::update_var( shift, \%{ $self->{'setup'} } );
	$self->_install();
	${ $self->{'module'} }{'access'}->register_module(	{
		module => ref( $self ),
		user_id => ${ ${ $self->{'setup'} }{'access'} }{'user_id'},
		group_id => ${ ${ $self->{'setup'} }{'access'} }{'group_id'},
		access => ${ ${ $self->{'setup'} }{'access'} }{'modus'}
	}	);
	$self->{'name_required'} = 1;
	$self->{'email_required'} = 1;
	$self->{'email_data_type'} = 'email';
	$self->{'name_data_type'} = 'tagout';
	$self->{'access_data_type'} = 'modus';
	return $self;
}

### internal functions ###

1;
