CREATE TABLE e107_qotd (
  qotd_id int(11) unsigned NOT NULL auto_increment,
  qotd_quote text NOT NULL,
  qotd_attribution varchar(150) NOT NULL default '',
  qotd_info varchar(255) NOT NULL default '',
  qotd_tags varchar(255) NOT NULL default '',
  qotd_source_url varchar(255) NOT NULL default '',
  qotd_collection varchar(64) NOT NULL default 'default',
  qotd_pin_start date DEFAULT NULL,
  qotd_pin_end date DEFAULT NULL,
  qotd_active tinyint(1) unsigned NOT NULL default '1',
  PRIMARY KEY (qotd_id),
  KEY qotd_collection (qotd_collection),
  KEY qotd_active (qotd_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
