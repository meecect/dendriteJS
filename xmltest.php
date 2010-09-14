<?php
// The file test.xml contains an XML document with a root element
// and at least an element /[root]/title.

if (file_exists('freemind.mm')) {
    $xml = simplexml_load_file('freemind.mm');
?>
<pre>
<?php
   print_r($xml);
?>
</pre>
<?php
} else {
    exit('Failed to open contents.xml.');
}
?>