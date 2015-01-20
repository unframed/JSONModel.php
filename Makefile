DEPS = deps \
	deps/test-more-php \
	deps/test_sites.php \
	deps/JSONMessage.php \
	deps/SQLAbstract.php

test: pull

pull: ${DEPS}
	cd deps/JSONMessage.php && git pull origin
	cd deps/SQLAbstract.php && git pull origin

deps:
	mkdir -p deps

deps/test-more-php:
	svn checkout http://test-more-php.googlecode.com/svn/trunk/ deps/test-more-php

deps/test_sites.php:
	git clone \
		https://github.com/unframed/test_sites.php \
		deps/test_sites.php

deps/JSONMessage.php:
	git clone \
		https://github.com/laurentszyster/JSONMessage.php.git \
		deps/JSONMessage.php

deps/SQLAbstract.php:
	git clone \
		https://github.com/unframed/SQLAbstract.php.git \
		deps/SQLAbstract.php