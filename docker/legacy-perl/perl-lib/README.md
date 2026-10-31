# Perl libraries for the 5.10 oracle

Populate this directory before building the image, from
`C:\Users\christian\Nextcloud\Perl-Lib`:

```bash
cp -r /mnt/c/Users/christian/Nextcloud/Perl-Lib/{PAB2,pab3,ssms} \
      docker/legacy-perl/perl-lib/
```

Expected layout after copying (mirrors what `mod_perl-startup.pl` put on
`@INC`):

```
perl-lib/
├── PAB/            <- from PAB2/blib/lib
├── PAB3/           <- from pab3/blib/lib (plus pab3/xs for the XS build)
├── ssms/
└── ...
```

Notes:

- These libraries are **not** part of this repository and must not be added to
  it: they are abandoned third-party code with an unclear licence.
- The XS parts (`PAB3`, `PAB3::Utils`, `PAB3::Crypt::XOR`,
  `PAB3::DB::Driver::Mysql`) are compiled **inside** the image against the
  Perl 5.10 built there. Do not copy `.so` files from the host or from the
  server — a mismatched `libmysqlclient` is precisely the fault being
  reproduced (`plan.md` §2).
- If `libmariadb-dev` on the base image is too new for the old driver sources,
  adjust the build in `pab3/xs/PAB3` — but keep this image disposable. It is a
  test oracle, never a target.
