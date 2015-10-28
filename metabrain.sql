CREATE TABLE IF NOT EXISTS projects (
id INT(11) default NULL auto_increment,
url varchar(512) default NULL,
title varchar(512) default NULL,
kind varchar(512) default NULL,
area varchar(512) default NULL,
status varchar(512) default NULL,
description varchar(512) default NULL,
logo varchar(512) default NULL,
hashtags varchar(512) default NULL,
categories varchar(512) default NULL,
orgacontact_name varchar(512) default NULL,
orgacontact_email varchar(512) default NULL,
orgacontact_language varchar(512) default NULL,
contact_email varchar(512) default NULL,
contact_phone varchar(512) default NULL,
contact_socialmedia_fb varchar(512) default NULL,
contact_socialmedia_twitter varchar(512) default NULL,
contact_adress_street varchar(512) default NULL,
contact_adress_housenr varchar(512) default NULL,
contact_adress_postalcode varchar(512) default NULL,
contact_adress_city varchar(512) default NULL,
contact_adress_state varchar(512) default NULL,
contact_adress_country varchar(512) default NULL,
code_repository varchar(512) default NULL,
programming_languages varchar(512) default NULL,
languages varchar(512) default NULL,
organization_type varchar(512) default NULL,
organization_name varchar(512) default NULL,
code_license varchar(512) default NULL,
releasedate varchar(512) default NULL,
entrydate varchar(512) default NULL,
software_development_needs varchar(512) default NULL,
random_generated_key varchar(512) default NULL,
PRIMARY KEY (id)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS user (
id INT(11) default NULL auto_increment,
email varchar(512) default NULL,
password varchar(512) default NULL,
PRIMARY KEY (id)
) ENGINE=MyISAM

INSERT INTO `user` (`id`, `email`, `password`) VALUES
(1, 'admin', '5f4dcc3b5aa765d61d8327deb882cf99');

