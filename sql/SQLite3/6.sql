-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details


-- set version marker
pragma user_version = 7;
update arsse_meta set value = '7' where "key" = 'schema_version';
