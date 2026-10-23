# The harness's FTP server: Pure-FTPd on the PHP image's Debian (its layers
# already there), one system user chrooted in its home, passive ports
# 30000-30009, in the foreground.
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends pure-ftpd \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --create-home --uid 1001 omnidrive \
    && echo 'omnidrive:omnidrive-harness' | chpasswd
EXPOSE 21 30000-30009
ENTRYPOINT []
# -A every user chrooted, -E no anonymous login, -p the passive ports, -C/-c clients per address and in all.
CMD ["pure-ftpd", "-A", "-E", "-p", "30000:30009", "-C", "50", "-c", "100"]
