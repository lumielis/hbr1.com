# Throwaway Perl 5.10 oracle
#
# Purpose: run the legacy CGI code so the PHP port can be diffed against real
# behaviour (plan.md §2, §8). Production is not a usable oracle - it runs on one
# surviving interpreter and half the entry points already return 500.
#
# This image is DISPOSABLE: it exists for the duration of W3-W8 and is deleted
# at cutover. It is not a second production stack.
#
# Build (from the repository root):
#   docker build -f docker/legacy-perl/Dockerfile -t hbr1/legacy-perl:5.10 .
#
# Run:
#   docker run --rm -p 8080:80 \
#     -v "$PWD/legacy/archive/cgi-bin:/var/www/cgi-bin:ro" \
#     -v "$PWD/public:/var/www/htdocs:ro" \
#     -v "$PWD/legacy/archive/template:/var/www/template:ro" \
#     hbr1/legacy-perl:5.10
#
# The bind-mount paths follow the cleanup: the Perl tree and the templates were
# archived, and the document root the scripts expect (`/var/www/htdocs`) is now
# the live `public/`, which still holds the legacy pages verbatim.
#
# The Perl libraries (PAB2, PAB3, ssms) are NOT part of this repository. Copy
# them into docker/legacy-perl/perl-lib/ from C:\Users\christian\Nextcloud\Perl-Lib
# before building; .gitignore already excludes them.
#
# WHY EVERY WORKAROUND BELOW EXISTS
# ---------------------------------
# This file is the record of what a 2009-era Perl codebase needs to run on a
# 2023-era toolchain. Each deviation from "just build it" is load-bearing; read
# the comment before removing one:
#
#   * -Dlibs='-lm ...'      glibc 2.34 no longer links libm implicitly, so
#                           miniperl fails with "undefined reference to pow".
#   * -fcommon              gcc 10+ defaults to -fno-common; the old XS sources
#                           rely on tentative definitions.
#   * DBI 1.616             current DBI needs a newer perl than 5.10.
#   * DBD::mysql 4.052      patched: MariaDB dropped MYSQL->reconnect, which
#                           older releases assign unconditionally.
#   * my_set_error rename   MariaDB's mysql.h now exports my_set_error() with a
#                           different signature, colliding with the driver's own
#                           helper of that name.
#   * libmariadb, not       libmysqlclient no longer exists as a Debian package.
#     libmysqlclient
#   * SHELL pipefail        otherwise `make ... | tail` masks a failed build and
#                           the image "succeeds" with no perl in it.

FROM debian:bookworm

ENV DEBIAN_FRONTEND=noninteractive
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential curl ca-certificates \
        libssl-dev zlib1g-dev libexpat1-dev \
        apache2 \
        mariadb-client libmariadb-dev libmariadb-dev-compat \
        libgd-dev libxml2-dev libwww-perl \
        libexpat1-dev zlib1g-dev \
        libmagickcore-dev libmagickwand-dev \
        libgtop2-dev \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# Perl 5.10.1
#
# This is the whole point of the image: the legacy `.cgi` scripts use
# `defined %hash`, which became a compile-time *error* in 5.24. Anything newer
# than 5.10 cannot even parse them, so the interpreter is built from source
# rather than pulled from a package.
#
# Building it here (rather than copying /usr/src/perl-5.10.1 from the server)
# means the XS modules compile against a known libc and a known libmariadb,
# which is exactly the mismatch that broke production.
# ---------------------------------------------------------------------------
ARG PERL_VERSION=5.10.1
ARG PERL_URL=https://www.cpan.org/src/5.0/perl-5.10.1.tar.gz

RUN mkdir -p /usr/src/perl && \
    curl -fsSL "$PERL_URL" -o /tmp/perl.tar.gz && \
    tar -xzf /tmp/perl.tar.gz -C /usr/src/perl --strip-components=1 && \
    rm /tmp/perl.tar.gz

WORKDIR /usr/src/perl

RUN ./Configure -des \
        -Dprefix=/opt/perl \
        -Dman1dir=none -Dman3dir=none \
        -Doptimize='-O2 -fno-strict-aliasing -fcommon' \
        -Dlibs='-lm -ldl -lcrypt -lpthread' && \
    make -j"$(nproc)" && \
    make install

ENV PATH=/opt/perl/bin:$PATH

RUN perl -v | head -2 && perl -MConfig -e 'print "archname: $Config{archname}\n"'

# ---------------------------------------------------------------------------
# Pure-Perl CPAN prerequisites
#
# Pinned tarballs, not the CPAN shell: CPAN's default mirror list is unusable
# in a build container, and "latest" of each of these has long since dropped
# 5.10 support.
# ---------------------------------------------------------------------------
RUN set -eux; \
    build() { \
        mkdir -p /tmp/cpan && cd /tmp/cpan && \
        curl -fsSL "$1" -o pkg.tgz && \
        tar -xzf pkg.tgz && cd "$2" && \
        perl Makefile.PL && make && make install; \
    }; \
    build https://cpan.metacpan.org/authors/id/M/MS/MSCHOUT/Env-C-0.15.tar.gz       Env-C-0.15; \
    build https://cpan.metacpan.org/authors/id/M/MA/MATTN/Devel-CheckLib-1.16.tar.gz Devel-CheckLib-1.16; \
    build https://cpan.metacpan.org/authors/id/T/TI/TIMB/DBI-1.616.tar.gz           DBI-1.616; \
    rm -rf /tmp/cpan

# ---------------------------------------------------------------------------
# cpanm, then everything the CGI entry points actually `use`
#
# cpanm is used rather than hand-pinned tarballs for the rest because
# libwww-perl alone pulls in HTTP::Message, HTTP::Date, HTML::Parser, URI, ... and
# pinning each by hand is a list that silently goes stale. The mirror is pinned so
# resolution is reproducible.
#
# The versions cpanm picks are the current releases; all of these still declare
# support back to 5.8, so none of them can be the reason a build fails.
# ---------------------------------------------------------------------------
RUN set -eux; \
    curl -fsSL https://cpan.metacpan.org/authors/id/M/MI/MIYAGAWA/App-cpanminus-1.7047.tar.gz -o /tmp/cpanm.tgz && \
    tar -xzf /tmp/cpanm.tgz -C /tmp && \
    cp /tmp/App-cpanminus-1.7047/bin/cpanm /usr/local/bin/cpanm && \
    chmod +x /usr/local/bin/cpanm && \
    rm -rf /tmp/cpanm.tgz /tmp/App-cpanminus-1.7047

RUN set -eux; \
    cpanm --notest --no-man-pages --mirror https://cpan.metacpan.org \
        CGI \
        LWP::Simple LWP::UserAgent Net::FTP \
        XML::Parser XML::LibXML \
        MIME::QuotedPrint \
        GD GD::SecurityImage GTop

# Image::Magick is optional. It is only reachable from
# cgi-bin/include/client/gallery.pl, and gallery is a W5 concern that the PHP
# port replaces outright. Its CPAN build is fragile (it wants to run
# ImageMagick's own configure against the installed perl) and a failure here
# must not stop the oracle from serving everything else.
RUN cpanm --notest --no-man-pages --mirror https://cpan.metacpan.org Image::Magick \
        || echo "Image::Magick unavailable - gallery.cgi image work will not run"

# The legacy code also `use`s these, and they are core-but-often-stripped.
RUN cpanm --notest --no-man-pages --mirror https://cpan.metacpan.org \
        Digest::MD5 Time::HiRes Time::Local IO::Socket::INET

RUN rm -rf /root/.cpanm /root/.cpan 2>/dev/null || true

# ---------------------------------------------------------------------------
# DBD::mysql (for the pure-Perl ssms layer)
#
# ssms's dbi/driver/mysql.pl calls DBI->connect directly, so it needs a real
# DBD::mysql - the PAB3 driver below does not satisfy it.
# ---------------------------------------------------------------------------
RUN set -eux; \
    mkdir -p /tmp/dbd && cd /tmp/dbd && \
    curl -fsSL https://cpan.metacpan.org/authors/id/D/DV/DVEEDEN/DBD-mysql-4.052.tar.gz -o dbd.tgz && \
    tar -xzf dbd.tgz && cd DBD-mysql-4.052 && \
    sed -i 's|^\(\s*\)result->reconnect\s*=\s*0;|\1/* removed: MYSQL has no reconnect member in MariaDB */|' dbdimp.c && \
    perl Makefile.PL --mysql_config=/usr/bin/mariadb_config && \
    make && make install

# ---------------------------------------------------------------------------
# PAB2 (used by apax.cgi and the PAB-era client modules)
#
# Makefile.PL emits harmless warnings for the absent lib/PAB/ConfigFile.pm and
# for the unresolvable Env::C prerequisite (installed above); both are expected.
# ---------------------------------------------------------------------------
COPY docker/legacy-perl/perl-lib/PAB2 /src/PAB2

WORKDIR /src/PAB2

RUN perl Makefile.PL && make -j"$(nproc)" && make install

# ---------------------------------------------------------------------------
# PAB3 (used by gallery.cgi and earthcam.cgi)
#
# The core build covers PAB3, ::Utils, ::Crypt::XOR and ::Test. The database
# drivers are separate Makefile.PLs and are built individually.
# ---------------------------------------------------------------------------
COPY docker/legacy-perl/perl-lib/pab3 /src/pab3

WORKDIR /src/pab3

RUN perl Makefile.PL && make -j"$(nproc)" && make install

# The MySQL driver. Two environment mismatches have to be handled:
#
#   1. Makefile.PL probes for `libmysqlclient` via mysql_config and finds
#      nothing, because Debian has shipped MariaDB for years. The override flags
#      let it find libmariadb and libz instead.
#   2. MariaDB's <mysql.h> declares my_set_error(MYSQL *, unsigned int, ...),
#      which collides with this driver's own my_set_error(my_cxt_t *, ...)
#      helper. Renaming the driver's copy is the smallest possible change and
#      keeps the vendored source otherwise untouched.
#
# This module is the one that segfaults under mod_perl in production, so the
# build succeeding *and* loading is the test that matters.
RUN set -eux; \
    cd /src/pab3/xs/PAB3/DB/Driver/Mysql && \
    sed -i 's/\bmy_set_error\b/pab3_my_set_error/g' my_mysql.c my_mysql.h && \
    perl Makefile.PL \
        --include="-I/usr/include/mariadb" \
        --libs="-L/usr/lib/x86_64-linux-gnu -lmariadb -lz" && \
    make && make install

# ---------------------------------------------------------------------------
# ssms (pure Perl)
#
# Not a CPAN distribution and has no Makefile.PL: the legacy code pulls it in
# with `require $path{'ssms'} . 'dbi.pl'`, so it only has to exist at the path
# settings.inc expects. See the symlink below.
# ---------------------------------------------------------------------------
COPY docker/legacy-perl/perl-lib/ssms /opt/perl-lib/ssms

# ---------------------------------------------------------------------------
# Apache with plain CGI (NOT mod_perl)
#
# mod_perl is the segfault surface in production; the oracle deliberately uses
# mod_cgi so the comparison exercises the script logic, not the interpreter
# embedding.
# ---------------------------------------------------------------------------
RUN a2enmod cgi && a2dissite 000-default

COPY docker/legacy-perl/apache.conf /etc/apache2/sites-available/legacy.conf
RUN a2ensite legacy

# settings.inc and config.pab.pl hardcode the production document root. Rather
# than patching those files (they are the legacy source of truth and must stay
# byte-faithful), the production tree is reproduced as symlinks. This is also
# what makes `require './include/init.pl'` resolve: the scripts derive
# SCRIPT_PATH from $0, which is under /var/www/cgi-bin.
RUN mkdir -p /home/christian/WWWRoot && \
    ln -s /var/www           /home/christian/WWWRoot/www.hbr1.com && \
    ln -s /opt/perl-lib/ssms /home/christian/WWWRoot/perl-ssms

# ---------------------------------------------------------------------------
# Shebang normalisation
#
# The entry points hardcode three different interpreters, and that split is the
# single reason production is half-broken (plan.md §2):
#
#   #!/usr/bin/perl        15 scripts
#   #!/opt/perl/bin/perl    6 scripts
#   #!/usr/local/bin/perl   4 scripts
#
# Rewriting the shebangs would modify the legacy source, so instead all three
# absolute paths are made to resolve to the 5.10.1 built above. That is what
# production should have done years ago; doing it here means every entry point
# is exercised against the interpreter that can actually parse it.
#
# This must run AFTER the last apt/a2enmod call: Debian's a2enmod and friends are
# Perl scripts of their own and expect the system interpreter.
# ---------------------------------------------------------------------------
RUN set -eux; \
    mv /usr/bin/perl /usr/bin/perl.system; \
    ln -s /opt/perl/bin/perl /usr/bin/perl; \
    ln -s /opt/perl/bin/perl /usr/local/bin/perl; \
    for p in /usr/bin/perl /opt/perl/bin/perl /usr/local/bin/perl; do \
        test "$("$p" -e 'print $]')" = "5.010001"; \
    done

# Fail the build, not the first request, if any legacy module is missing.
RUN set -eux; \
    cd /tmp && \
    perl -e 'use PAB;               print "PAB     $PAB::VERSION\n"'; \
    perl -e 'use PAB3;              print "PAB3    $PAB3::VERSION\n"'; \
    perl -e 'use DBI;               print "DBI     $DBI::VERSION\n"'; \
    perl -e 'use DBD::mysql;        print "DBD     $DBD::mysql::VERSION\n"'; \
    perl -e 'use PAB3::DB::Driver::Mysql; print "PAB3::DB::Driver::Mysql loaded\n"'

ENV PERL5LIB=/opt/perl-lib

EXPOSE 80
CMD ["apachectl", "-D", "FOREGROUND"]
