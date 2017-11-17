<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

return [
    'Driver.Db.SQLite3.Name'                                               => 'SQLite 3',
    'Driver.Service.Curl.Name'                                             => 'HTTP (curl)',
    'Driver.Service.Internal.Name'                                         => 'Internal',
    'Driver.User.Internal.Name'                                            => 'Internal',

    'HTTP.Status.100'                                                      => 'Continue',
    'HTTP.Status.101'                                                      => 'Switching Protocols',
    'HTTP.Status.102'                                                      => 'Processing',
    'HTTP.Status.200'                                                      => 'OK',
    'HTTP.Status.201'                                                      => 'Created',
    'HTTP.Status.202'                                                      => 'Accepted',
    'HTTP.Status.203'                                                      => 'Non-Authoritative Information',
    'HTTP.Status.204'                                                      => 'No Content',
    'HTTP.Status.205'                                                      => 'Reset Content',
    'HTTP.Status.206'                                                      => 'Partial Content',
    'HTTP.Status.207'                                                      => 'Multi-Status',
    'HTTP.Status.208'                                                      => 'Already Reported',
    'HTTP.Status.226'                                                      => 'IM Used',
    'HTTP.Status.300'                                                      => 'Multiple Choice',
    'HTTP.Status.301'                                                      => 'Moved Permanently',
    'HTTP.Status.302'                                                      => 'Found',
    'HTTP.Status.303'                                                      => 'See Other',
    'HTTP.Status.304'                                                      => 'Not Modified',
    'HTTP.Status.305'                                                      => 'Use Proxy',
    'HTTP.Status.306'                                                      => 'Switch Proxy',
    'HTTP.Status.307'                                                      => 'Temporary Redirect',
    'HTTP.Status.308'                                                      => 'Permanent Redirect',
    'HTTP.Status.400'                                                      => 'Bad Request',
    'HTTP.Status.401'                                                      => 'Unauthorized',
    'HTTP.Status.402'                                                      => 'Payment Required',
    'HTTP.Status.403'                                                      => 'Forbidden',
    'HTTP.Status.404'                                                      => 'Not Found',
    'HTTP.Status.405'                                                      => 'Method Not Allowed',
    'HTTP.Status.406'                                                      => 'Not Acceptable',
    'HTTP.Status.407'                                                      => 'Proxy Authentication Required',
    'HTTP.Status.408'                                                      => 'Request Timeout',
    'HTTP.Status.409'                                                      => 'Conflict',
    'HTTP.Status.410'                                                      => 'Gone',
    'HTTP.Status.411'                                                      => 'Length Required',
    'HTTP.Status.412'                                                      => 'Precondition Failed',
    'HTTP.Status.413'                                                      => 'Payload Too Large',
    'HTTP.Status.414'                                                      => 'URL Too Long',
    'HTTP.Status.415'                                                      => 'Unsupported Media Type',
    'HTTP.Status.416'                                                      => 'Range Not Satisfiable',
    'HTTP.Status.417'                                                      => 'Expectation Failed',
    'HTTP.Status.421'                                                      => 'Misdirected Request',
    'HTTP.Status.422'                                                      => 'Unprocessable Entity',
    'HTTP.Status.423'                                                      => 'Locked',
    'HTTP.Status.424'                                                      => 'Failed Depedency',
    'HTTP.Status.426'                                                      => 'Upgrade Required',
    'HTTP.Status.428'                                                      => 'Precondition Failed',
    'HTTP.Status.429'                                                      => 'Too Many Requests',
    'HTTP.Status.431'                                                      => 'Request Header Fields Too Large',
    'HTTP.Status.451'                                                      => 'Unavailable For Legal Reasons',
    'HTTP.Status.500'                                                      => 'Internal Server Error',
    'HTTP.Status.501'                                                      => 'Not Implemented',
    'HTTP.Status.502'                                                      => 'Bad Gateway',
    'HTTP.Status.503'                                                      => 'Service Unavailable',
    'HTTP.Status.504'                                                      => 'Gateway Timeout',
    'HTTP.Status.505'                                                      => 'HTTP Version Not Supported',
    'HTTP.Status.506'                                                      => 'Variant Also Negotiates',
    'HTTP.Status.507'                                                      => 'Insufficient Storage',
    'HTTP.Status.508'                                                      => 'Loop Detected',
    'HTTP.Status.510'                                                      => 'Not Extended',
    'HTTP.Status.511'                                                      => 'Network Authentication Required',

    // this should only be encountered in testing (because tests should cover all exceptions!)
    'Exception.JKingWeb/Arsse/Exception.uncoded'                           => 'The specified exception symbol {0} has no code specified in AbstractException.php',
    // this should not usually be encountered
    'Exception.JKingWeb/Arsse/Exception.unknown'                           => 'An unknown error has occurred',
    'Exception.JKingWeb/Arsse/ExceptionType.strictFailure'                 => 'Supplied value could not be normalized to {0, select,
        1 {null}
        2 {boolean}
        3 {integer}
        4 {float}
        5 {datetime}
        6 {string}
        7 {array}
        other {requested type}
     }',
    'Exception.JKingWeb/Arsse/ExceptionType.typeUnknown'                   => 'Normalization type {0} is  not implemented',
    'Exception.JKingWeb/Arsse/Lang/Exception.defaultFileMissing'           => 'Default language file "{0}" missing',
    'Exception.JKingWeb/Arsse/Lang/Exception.fileMissing'                  => 'Language file "{0}" is not available',
    'Exception.JKingWeb/Arsse/Lang/Exception.fileUnreadable'               => 'Insufficient permissions to read language file "{0}"',
    'Exception.JKingWeb/Arsse/Lang/Exception.fileCorrupt'                  => 'Language file "{0}" is corrupt or does not conform to expected format',
    'Exception.JKingWeb/Arsse/Lang/Exception.stringMissing'                => 'Message string "{msgID}" missing from all loaded language files ({fileList})',
    'Exception.JKingWeb/Arsse/Lang/Exception.stringInvalid'                => 'Message string "{msgID}" is not a valid ICU message string (language files loaded: {fileList})',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileMissing'                  => 'Configuration file "{0}" does not exist',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileUnreadable'               => 'Insufficient permissions to read configuration file "{0}"',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileUncreatable'              => 'Insufficient permissions to write new configuration file "{0}"',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileUnwritable'               => 'Insufficient permissions to overwrite configuration file "{0}"',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileCorrupt'                  => 'Configuration file "{0}" is corrupt or does not conform to expected format',
    'Exception.JKingWeb/Arsse/Db/Exception.extMissing'                     => 'Required PHP extension for driver "{0}" not installed',
    'Exception.JKingWeb/Arsse/Db/Exception.fileMissing'                    => 'Database file "{0}" does not exist',
    'Exception.JKingWeb/Arsse/Db/Exception.fileUnreadable'                 => 'Insufficient permissions to open database file "{0}" for reading',
    'Exception.JKingWeb/Arsse/Db/Exception.fileUnwritable'                 => 'Insufficient permissions to open database file "{0}" for writing',
    'Exception.JKingWeb/Arsse/Db/Exception.fileUnusable'                   => 'Insufficient permissions to open database file "{0}" for reading or writing',
    'Exception.JKingWeb/Arsse/Db/Exception.fileUncreatable'                => 'Insufficient permissions to create new database file "{0}"',
    'Exception.JKingWeb/Arsse/Db/Exception.fileCorrupt'                    => 'Database file "{0}" is corrupt or not a valid database',
    'Exception.JKingWeb/Arsse/Db/Exception.paramTypeInvalid'               => 'Prepared statement parameter type "{0}" is invalid',
    'Exception.JKingWeb/Arsse/Db/Exception.paramTypeUnknown'               => 'Prepared statement parameter type "{0}" is valid, but not implemented',
    'Exception.JKingWeb/Arsse/Db/Exception.paramTypeMissing'               => 'Prepared statement parameter type for parameter #{0} was not specified',
    'Exception.JKingWeb/Arsse/Db/Exception.updateManual'                   =>
        '{from_version, select,
            0 {{driver_name} database is configured for manual updates and is not initialized; please populate the database with the base schema}
            other {{driver_name} database is configured for manual updates; please update from schema version {current} to version {target}}
        }',
    'Exception.JKingWeb/Arsse/Db/Exception.updateManualOnly'               =>
        '{from_version, select,
            0 {{driver_name} database must be updated manually and is not initialized; please populate the database with the base schema}
            other {{driver_name} database must be updated manually; please update from schema version {current} to version {target}}
        }',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileMissing'              => 'Automatic updating of the {driver_name} database failed due to instructions for updating from version {current} not being available',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileUnreadable'           => 'Automatic updating of the {driver_name} database failed due to insufficient permissions to read instructions for updating from version {current}',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileUnusable'             => 'Automatic updating of the {driver_name} database failed due to an error reading instructions for updating from version {current}',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileError'                => 'Automatic updating of the {driver_name} database failed updating from version {current} with the following error: "{message}"',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileIncomplete'           => 'Automatic updating of the {driver_name} database failed due to instructions for updating from version {current} being incomplete',
    'Exception.JKingWeb/Arsse/Db/Exception.updateTooNew'                   =>
        '{difference, select,
            0 {Automatic updating of the {driver_name} database failed because it is already up to date with the requested version, {target}}
            other {Automatic updating of the {driver_name} database failed because its version, {current}, is newer than the requested version, {target}}
        }',
    'Exception.JKingWeb/Arsse/Db/Exception.engineErrorGeneral'             => '{0}',
    'Exception.JKingWeb/Arsse/Db/Exception.savepointStatusUnknown'         => 'Savepoint status code {0} not implemented',
    'Exception.JKingWeb/Arsse/Db/Exception.savepointInvalid'               => 'Tried to {action} invalid savepoint {index}',
    'Exception.JKingWeb/Arsse/Db/Exception.savepointStale'                 => 'Tried to {action} stale savepoint {index}',
    'Exception.JKingWeb/Arsse/Db/Exception.resultReused'                   => 'Result set already iterated',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.missing'                   => 'Required field "{field}" missing while performing action "{action}"',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.whitespace'                => 'Field "{field}" of action "{action}" may not contain only whitespace',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.tooLong'                   => 'Field "{field}" of action "{action}" has a maximum length of {max}',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.tooShort'                  => 'Field "{field}" of action "{action}" has a minimum length of {min}',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.typeViolation'             => 'Field "{field}" of action "{action}" expects a value of type "{type}"',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.subjectMissing'            => 'Referenced ID ({id}) in field "{field}" does not exist',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.idMissing'                 => 'Referenced ID ({id}) in field "{field}" does not exist',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.circularDependence'        => 'Referenced ID ({id}) in field "{field}" creates a circular dependence',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.constraintViolation'       => 'Specified value in field "{0}" already exists',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.engineConstraintViolation' => '{0}',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.engineTypeViolation'       => '{0}',
    'Exception.JKingWeb/Arsse/Db/ExceptionTimeout.general'                 => '{0}',
    'Exception.JKingWeb/Arsse/User/Exception.alreadyExists'                => 'Could not perform action "{action}" because the user {user} already exists',
    'Exception.JKingWeb/Arsse/User/Exception.doesNotExist'                 => 'Could not perform action "{action}" because the user {user} does not exist',
    'Exception.JKingWeb/Arsse/User/Exception.authMissing'                  => 'Please log in to proceed',
    'Exception.JKingWeb/Arsse/User/Exception.authFailed'                   => 'Authentication failed',
    'Exception.JKingWeb/Arsse/User/ExceptionAuthz.notAuthorized'           =>
        '{action, select,
            userList {{user, select,
                global {Authenticated user is not authorized to view the global user list}
                other {Authenticated user is not authorized to view the user list for domain {user}}
            }}
            other {Authenticated user is not authorized to perform the action "{action}" on behalf of {user}}
        }',
    'Exception.JKingWeb/Arsse/Feed/Exception.invalidCertificate'           => 'Could not download feed "{url}" because its server is serving an invalid SSL certificate',
    'Exception.JKingWeb/Arsse/Feed/Exception.invalidUrl'                   => 'Feed URL "{url}" is invalid',
    'Exception.JKingWeb/Arsse/Feed/Exception.maxRedirect'                  => 'Could not download feed "{url}" because its server reached its maximum number of HTTP redirections',
    'Exception.JKingWeb/Arsse/Feed/Exception.maxSize'                      => 'Could not download feed "{url}" because its size exceeds the maximum allowed on its server',
    'Exception.JKingWeb/Arsse/Feed/Exception.timeout'                      => 'Could not download feed "{url}" because its server timed out',
    'Exception.JKingWeb/Arsse/Feed/Exception.forbidden'                    => 'Could not download feed "{url}" because you do not have permission to access it',
    'Exception.JKingWeb/Arsse/Feed/Exception.unauthorized'                 => 'Could not download feed "{url}" because you provided insufficient or invalid credentials',
    'Exception.JKingWeb/Arsse/Feed/Exception.malformedXml'                 => 'Could not parse feed "{url}" because it is malformed',
    'Exception.JKingWeb/Arsse/Feed/Exception.xmlEntity'                    => 'Refused to parse feed "{url}" because it contains an XXE attack',
    'Exception.JKingWeb/Arsse/Feed/Exception.subscriptionNotFound'         => 'Unable to find a feed at location "{url}"',
    'Exception.JKingWeb/Arsse/Feed/Exception.unsupportedFeedFormat'        => 'Feed "{url}" is of an unsupported format',
];