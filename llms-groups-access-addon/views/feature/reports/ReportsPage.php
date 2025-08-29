<?php
/** @var int   $user_id */
/** @var WP_Post[] $courses */
/** @var int   $course_id */
/** @var array $report_data */
$student = get_user_by( 'ID', $user_id );
?>
<div class="container mx-auto p-4">
  <h1 class="text-2xl mb-4">Report for <?php echo esc_html( $student->display_name ); ?></h1>

  <form method="get" class="mb-6">
    <label class="block text-sm font-medium mb-2">Select Course:</label>
    <select name="course_id" onchange="this.form.submit()" class="select select-bordered">
      <?php foreach ( $courses as $course ) : ?>
        <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course->ID, $course_id ); ?>>
          <?php echo esc_html( $course->post_title ); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php
  $data = $report_data[ $course_id ];
  ?>
  <div class="mb-6 space-x-4">
    <span><strong>Progress:</strong> <?php echo $data['complete'] . ' / ' . $data['total'] . ' (' . $data['pct_progress'] . '%)'; ?></span>
    <span><strong>Last Activity:</strong> <?php echo esc_html( $data['last_activity'] ); ?></span>
  </div>

  <div class="overflow-x-auto border rounded shadow">
    <table class="table-auto w-full text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left">Lesson</th>
          <th class="px-4 py-2">Completed</th>
          <th class="px-4 py-2">Quiz</th>
          <th class="px-4 py-2">Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $data['rows'] as $row ) : ?>
          <tr>
            <td class="border px-4 py-2"><?php echo esc_html( $row['title'] ); ?></td>
            <td class="border px-4 py-2"><?php echo esc_html( $row['completed_date'] ); ?></td>
            <td class="border px-4 py-2"><?php echo esc_html( $row['quiz_title'] ); ?></td>
            <td class="border px-4 py-2"><?php echo esc_html( $row['grade'] ); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
