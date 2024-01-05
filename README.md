# Extension for retrieving data from Audd.io

Audd.io is a commercial service which recognizes songs based on parts
of the audio file. This extensions extracts a couple of seconds from
an mpg3 file stored in SeedDMS, sends it to audd.io and caches and
evaluates the result. The returned data is used for

* a document preview image
* conversion into plain text and
* conversion into a preview image

The conversion into plain text just takes the artist, song title and
album into account. The preview image is derived from the album cover
image provided by spotify

Each request will include data from Music Brainz, spotify and apple
music.  The data from Music Brainz contains a list of releases which
is also shown in the document information section.

## API token

Audd.io has a very limited free service if no API token is provided.
This includes only a couple (about 10) request per day. More request
need to be paid starting at 5$ per month. The result of each request
is saved on disc and read from there for each repeating request.
The conversion to text and png also checks for a cached result before
sending a new request to Audd.io.

If you have bought additional request you can set your API token in
the configuration of the extension.
