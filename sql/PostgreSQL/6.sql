-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

alter table arsse_tokens add column data text default null;

alter table arsse_subscriptions add column keep_rule text default null;
alter table arsse_subscriptions add column block_rule text default null;
alter table arsse_marks add column hidden smallint not null default 0;

alter table arsse_users add column num bigint unique;
alter table arsse_users add column admin smallint not null default 0;
create temp table arsse_users_existing(
    id text not null,
    num bigserial
);
insert into arsse_users_existing(id) select id from arsse_users;
update arsse_users as u
    set num = e.num
from arsse_users_existing as e
where u.id = e.id;
drop table arsse_users_existing;
alter table arsse_users alter column num set not null;

create table arsse_user_meta(
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    key text not null,
    modified timestamp(0) without time zone not null default CURRENT_TIMESTAMP,
    value text,
    primary key(owner,key)
);

alter table arsse_subscriptions add column scrape smallint not null default 0;
update arsse_subscriptions set scrape = 1 where feed in (select id from arsse_feeds where scrape = 1);
alter table arsse_feeds drop column scrape;
alter table arsse_articles add column content_scraped text;

create table arsse_icons(
    id bigserial primary key,
    url text unique not null,
    modified timestamp(0) without time zone,
    etag text not null default '',
    next_fetch timestamp(0) without time zone,
    orphaned timestamp(0) without time zone,
    type text,
    data bytea
);
insert into arsse_icons(url) select distinct favicon from arsse_feeds where favicon is not null and favicon <> '';
alter table arsse_feeds add column icon bigint references arsse_icons(id) on delete set null;
update arsse_feeds as f set icon = i.id from arsse_icons as i where f.favicon = i.url;
alter table arsse_feeds drop column favicon;

update arsse_meta set value = '7' where "key" = 'schema_version';
