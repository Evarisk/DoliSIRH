'use strict';

const gulp   = require('gulp');
const sass   = require('gulp-sass')(require('sass'));
const rename = require('gulp-rename');
const uglify = require('gulp-uglify');
const concat = require('gulp-concat');

const paths = {
	scss_core: ['css/scss/**/*.scss', 'css/'],
	js_backend: ['js/dolisirh.js', 'js/modules/*.js']
};

/** Core */
gulp.task('scss_core', function() {
	return gulp.src(paths.scss_core[0])
		.pipe(sass({outputStyle: 'compressed'}).on('error', sass.logError))
		.pipe(rename('./dolisirh.min.css'))
		.pipe(gulp.dest(paths.scss_core[1]));
});

gulp.task('js_backend', function() {
	return gulp.src(paths.js_backend)
		.pipe(concat('dolisirh.min.js'))
		.pipe(uglify())
		.pipe(gulp.dest('./js/'))
});

/** Watch */
gulp.task('default', function() {
	gulp.watch(paths.scss_core[0], gulp.series('scss_core'));
	gulp.watch(paths.js_backend[1], gulp.series('js_backend'));
});