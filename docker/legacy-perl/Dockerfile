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
#     -v "$PWD/cgi-bin:/var/www/cgi-bin:ro" \
#     -v "$PWD/htdocs:/var/www/htdocs:ro" \
#     -v "$PWD/docker/legacy-perl/perl-lib:/opt/perl-lib:ro" \
#     hbr1/legacy-perl:5.10
#
# The Perl libraries (PAB2, PAB3, ssms) are NOT in this repository. Copy them
# into docker/legacy-perl/perl-lib/ from C:\Users\christian\Nextcloud\Perl-Lib
# before building: legacy-perl/perl-lib/PAB, PAB3, ssms, ...

FROM debian:bookworm

ENV DEBIAN_FRONTEND=noninteractive

# Build tooling. Perl 5.10 predates the modern toolchain, so it is compiled
# from source rather than pulled from a package.
RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential curl ca-certificates \
        libssl-dev zlib1g-dev libexpat1-dev \
        apache2 libapache2-mod-cgi \
        mariadb-client libmariadb-dev \
        libgd-dev libxml2-dev libwww-perl \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# Perl 5.10.1
#
# This is the whole point of the image: the PAB3 XS modules were built against
# 5.10 and segfault on anything newer. Building the interpreter here (rather
# than copying /usr/src/perl-5.10.1) means the XS gets compiled against a known
# libc and libmariadb, which is exactly the mismatch that broke production.
# ---------------------------------------------------------------------------
ARG PERL_VERSION=5.10.1
ARG PERL_URL=https://www.cpan.org/src/5.0/perl-5.10.1.tar.gz

RUN mkdir -p /usr/src/perl && \
    curl -fsSL "$PERL_URL" -o /tmp/perl.tar.gz && \
    tar -xzf /tmp/perl.tar.gz -C /usr/src/perl --strip-components=1 && \
    rm /tmp/perl.tar.gz && \
    cd /usr/src/perl && \
    ./Configure -des \
        -Dprefix=/opt/perl \
        -Dusethreads \
        -Duseshrplib \
        -Dman1dir=none -Dman3dir=none && \
    make -j"$(nproc)" && \
    make install

ENV PATH=/opt/perl/bin:$PATH

RUN perl -v | head -3 && perl -MConfig -e 'print "archname: $Config{archname}\n"'

# ---------------------------------------------------------------------------
# PAB3 XS layer
#
# Copied in only if present, so the image still builds on a checkout that does
# not have the Perl libraries.
# ---------------------------------------------------------------------------
COPY docker/legacy-perl/perl-lib/ /opt/perl-lib/

# DBI is required by the pure-Perl ssms layer. Installed against the freshly
# built 5.10, not the system perl.
RUN if [ -d /opt/perl-lib/PAB3 ]; then \
        cd /opt/perl-lib/PAB3 && \
        perl Makefile.PL && make && make install ; \
    fi

# ---------------------------------------------------------------------------
# Apache with plain CGI (NOT mod_perl)
#
# mod_perl is the segfault surface in production; the oracle deliberately uses
# mod_cgi so the comparison exercises the script logic, not the interpreter
# embedding.
# ---------------------------------------------------------------------------
RUN a2enmod cgi && \
    a2dissite 000-default

COPY docker/legacy-perl/apache.conf /etc/apache2/sites-available/legacy.conf
RUN a2ensite legacy

ENV PERL5LIB=/opt/perl-lib

EXPOSE 80
CMD ["apachectl", "-D", "FOREGROUND"]
