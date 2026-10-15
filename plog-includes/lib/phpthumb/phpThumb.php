<?php
//////////////////////////////////////////////////////////////
//   phpThumb() by James Heinrich <info@silisoftware.com>   //
//        available at http://phpthumb.sourceforge.net      //
//         and/or https://github.com/JamesHeinrich/phpThumb //
//////////////////////////////////////////////////////////////
///                                                         //
// See: phpthumb.changelog.txt for recent changes           //
// See: phpthumb.readme.txt for usage instructions          //
//                                                         ///
//////////////////////////////////////////////////////////////

// Plogger uses phpthumb.class.php internally. The unaudited standalone HTTP
// endpoint is unnecessary and must not expose arbitrary source/filter options.
http_response_code(403);
exit('Standalone thumbnail access is disabled.');
