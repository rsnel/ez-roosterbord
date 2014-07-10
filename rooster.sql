-- example: mysql -u root -p --default-character-set utf8 < rooster.sql

-- for MSSQL use IDENTITY instead of AUTO_INCREMENT

-- DROP DATABASE rooster1112;
-- CREATE DATABASE rooster1112 COLLATE = 'utf8_unicode_ci';

-- CREATE DATABASE rooster1213 COLLATE = 'utf8_unicode_ci';

-- USE rooster1213;

-- we use this table to make sure the update scripts
-- runs only one at a time
CREATE TABLE roosters (
	rooster_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	week_id INTEGER,
	file_id INTEGER,
	basis_id INTEGER,
	wijz_id INTEGER,
	timestamp INTEGER
) ENGINE = MYISAM;
CREATE TABLE locking (
	locking_id INTEGER PRIMARY KEY,    -- we only use lock_id = 0
	locking_pid INTEGER,		-- PID of running update
	locking_status TEXT,		-- to show user the update status
	locking_last_timestamp INTEGER,	-- must be updated regularly during the update, if it's behind by say 60 seconds
					-- we assume the process is locked up or crashed
	locking_randid VARCHAR(32)
) ENGINE = MYISAM;
CREATE TABLE files (
	file_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	file_name VARCHAR(64),
	file_md5 VARCHAR(32),
	file_time INTEGER,
	file_type INTEGER, -- 0 index, 1 basis, 2 wijz
	file_version INTEGER,
	file_status INTEGER, -- 0 'not imported', 1 'imported'
	UNIQUE ( file_name, file_md5, file_time )
) ENGINE = MYISAM;
CREATE TABLE weken (
	week_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	year INTEGER,
	week INTEGER,
	ma INTEGER,
	di INTEGER,
	wo INTEGER,
	do INTEGER,
	vr INTEGER
) ENGINE = MYISAM;
CREATE TABLE entities (
	entity_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	entity_name VARCHAR(32),
	entity_type INTEGER,
	entity_active INTEGER,
	UNIQUE (entity_name)
) ENGINE = MYISAM;
CREATE INDEX entity_types ON entities ( entity_type );

-- wildcard
INSERT INTO entities ( entity_name, entity_type ) VALUES ( '*', 0 );

-- zermelo_id's are weird, they have a letter at the end (almost always)
-- we archive them here and use numeric id's
CREATE TABLE zermelo_ids (
	zermelo_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	zermelo_id_orig VARCHAR(16),
	UNIQUE (zermelo_id)
) ENGINE = MYISAM;
CREATE INDEX orig2zermelo_id ON zermelo_ids ( zermelo_id_orig );

CREATE TABLE files2lessen (
	files2lessen_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	file_id INTEGER,
	zermelo_id INTEGER,
	les_id INTEGER,
	UNIQUE (file_id, zermelo_id, les_id)
) ENGINE = MYISAM;
CREATE INDEX files2lessen0 ON files2lessen ( file_id );
CREATE INDEX files2lessen1 ON files2lessen ( les_id );
CREATE INDEX files2lessen2 ON files2lessen ( file_id, zermelo_id );
CREATE TABLE lessen (
	les_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	dag INTEGER,
	uur INTEGER,
-- de laatste rijen worden gebruikt om het rooster snel te kunnen laten zien
	lesgroepen VARCHAR(512),
	vakken VARCHAR(64),
	docenten VARCHAR(64),
	lokalen VARCHAR(64),
-- notitie: de roostermakers kunnen een notitie plaatsen bij een wijziging
	notitie VARCHAR(256)
) ENGINE = MYISAM;
CREATE INDEX lessen ON lessen ( dag, uur );
CREATE TABLE entities2lessen (
	entities2lessen_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	entity_id INTEGER,
	les_id INTEGER,
	UNIQUE (entity_id, les_id)
) ENGINE = MYISAM;
CREATE INDEX entities2lessen0 ON entities2lessen ( entity_id );
CREATE INDEX entities2lessen1 ON entities2lessen ( les_id );
CREATE TABLE grp2ppl (
	grp2ppl_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	lesgroep_id INTEGER,
	ppl_id INTEGER,
	file_id_basis INTEGER,
	UNIQUE ( ppl_id, lesgroep_id, file_id_basis )
) ENGINE = MYISAM;
CREATE INDEX grp2ppl0 ON grp2ppl ( lesgroep_id, file_id_basis );
CREATE INDEX grp2ppl1 ON grp2ppl ( ppl_id, file_id_basis );
CREATE TABLE grp2grp (
	grp2ppl_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	lesgroep_id INTEGER,
	lesgroep2_id INTEGER,
	file_id_basis INTEGER,
	UNIQUE (lesgroep_id, lesgroep2_id, file_id_basis )
) ENGINE = MYISAM;
CREATE INDEX grp2grp0 ON grp2grp ( lesgroep_id, file_id_basis );
CREATE INDEX grp2grp1 ON grp2grp ( lesgroep2_id, file_id_basis );
CREATE TABLE names (
	name_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	entity_id INTEGER,
	name VARCHAR(224),	             -- for display and searching
	firstname VARCHAR(64),    	     -- for sorting
	prefix VARCHAR(32),       	     -- for sorting
	surname VARCHAR(128),     	     -- for sorting
	email VARCHAR(128),
	UNIQUE (entity_id)
) ENGINE = MYISAM;
CREATE TABLE berichten (
	bericht_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	bericht_title VARCHAR(128),
	bericht_body TEXT,
	bericht_visiblefrom INTEGER,
	bericht_visibleuntil INTEGER,
	bericht_update INTEGER
) ENGINE = MYISAM;
CREATE TABLE config (
        config_id INTEGER PRIMARY KEY AUTO_INCREMENT,
        config_key VARCHAR(32),
        config_value VARCHAR(1024),
        UNIQUE (config_key)
) ENGINE = MYISAM;
CREATE TABLE entities2berichten (
	entities2berichten_id INTEGER PRIMARY KEY AUTO_INCREMENT,
	entity_id INTEGER,
	bericht_id INTEGER,
	UNIQUE (entity_id, bericht_id)
) ENGINE = MYISAM;
