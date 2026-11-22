# TLC 2.19 / tla2tools v1.7.4, pinned by image digest and jar checksum.
# The image is built once by run.sh; nothing is installed on the host.
FROM eclipse-temurin:21-jre@sha256:d77e090ae1f6970c047b443c6f0a6c1358b0426d44432bb5a7753408cd43de0d
ADD https://github.com/tlaplus/tlaplus/releases/download/v1.7.4/tla2tools.jar /opt/tla2tools.jar
RUN echo "936a262061c914694dfd669a543be24573c45d5aa0ff20a8b96b23d01e050e88  /opt/tla2tools.jar" | sha256sum -c -
WORKDIR /spec
ENTRYPOINT ["java", "-XX:+UseParallelGC", "-cp", "/opt/tla2tools.jar", "tlc2.TLC"]
