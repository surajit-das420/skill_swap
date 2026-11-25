  <!-- Log in Modal -->
  <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-4 shadow">
        <div class="modal-header border-0 pb-0 px-4 pt-4">
          <div>
            <h5 class="modal-title fw-bold" id="loginModalLabel">Welcome back</h5>
            <p class="text-muted small mb-0">Log in to manage your skill swaps, messages, and reviews.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="loginForm">
          <div class="modal-body px-4 py-3">
            <div class="alert alert-danger py-2 small d-none" id="loginError"></div>
            <div class="mb-3">
              <label for="loginEmail" class="form-label small fw-semibold">Email</label>
              <input type="email" class="form-control ss-input" id="loginEmail" name="email" placeholder="sam@skillswap.local" required>
            </div>
            <div class="mb-3">
              <label for="loginPassword" class="form-label small fw-semibold">Password</label>
              <input type="password" class="form-control ss-input" id="loginPassword" name="password" placeholder="Enter your password" required>
            </div>
            <div class="bg-light rounded-3 px-3 py-2 d-flex flex-column gap-1">
              <div class="d-flex align-items-center justify-content-between">
                <span class="small text-muted">User: <strong>sam@skillswap.local</strong> / <code>password123</code></span>
                <button type="button" class="btn btn-sm btn-link text-decoration-none fw-semibold p-0" style="color: var(--ss-primary);" id="fillDemoLogin">Fill user</button>
              </div>
              <div class="d-flex align-items-center justify-content-between">
                <span class="small text-muted">Admin: <strong>admin@skillswap.local</strong> / <code>admin123</code></span>
                <button type="button" class="btn btn-sm btn-link text-decoration-none fw-semibold p-0" style="color: var(--ss-primary);" id="fillAdminLogin">Fill admin</button>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 px-4 pb-4 pt-1 d-flex justify-content-between">
            <button type="button" class="btn btn-link text-muted text-decoration-none small px-0" data-bs-toggle="modal" data-bs-target="#signupModal">Need an account? Sign up</button>
            <button type="submit" class="btn btn-ss-primary">Log in</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Sign up Modal -->
  <div class="modal fade" id="signupModal" tabindex="-1" aria-labelledby="signupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-4 shadow">
        <div class="modal-header border-0 pb-0 px-4 pt-4">
          <div>
            <h5 class="modal-title fw-bold" id="signupModalLabel">Create your SkillSwap account</h5>
            <p class="text-muted small mb-0">Pick what you can teach and what you want to learn.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="signupForm">
          <div class="modal-body px-4 py-3">
            <div class="alert alert-danger py-2 small d-none" id="signupError"></div>
            <div class="mb-3">
              <label for="signupName" class="form-label small fw-semibold">Full name</label>
              <input type="text" class="form-control ss-input" id="signupName" name="name" placeholder="e.g., Alex Rivera" required>
            </div>
            <div class="mb-3">
              <label for="signupEmail" class="form-label small fw-semibold">Email</label>
              <input type="email" class="form-control ss-input" id="signupEmail" name="email" placeholder="you@example.com" required>
            </div>
            <div class="mb-3">
              <label for="signupPassword" class="form-label small fw-semibold">Password</label>
              <input type="password" class="form-control ss-input" id="signupPassword" name="password" minlength="6" placeholder="At least 6 characters" required>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label for="signupTeach" class="form-label small fw-semibold">I can teach</label>
                <input type="text" class="form-control ss-input" id="signupTeach" name="teach_skills" placeholder="Guitar, Excel" required>
              </div>
              <div class="col-md-6">
                <label for="signupLearn" class="form-label small fw-semibold">I want to learn</label>
                <input type="text" class="form-control ss-input" id="signupLearn" name="learn_skills" placeholder="Python, French" required>
              </div>
            </div>
            <div class="mb-1">
              <label for="signupAvailability" class="form-label small fw-semibold">Availability</label>
              <input type="text" class="form-control ss-input" id="signupAvailability" name="availability" value="Weekends and evenings" required>
            </div>
          </div>
          <div class="modal-footer border-0 px-4 pb-4 pt-1 d-flex justify-content-between">
            <button type="button" class="btn btn-link text-muted text-decoration-none small px-0" data-bs-toggle="modal" data-bs-target="#loginModal">Already have an account?</button>
            <button type="submit" class="btn btn-ss-primary">Sign up</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Shared Partner Profile Modal (with Reviews & Exchange Request shortcut) -->
  <div class="modal fade" id="partnerProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 rounded-4 shadow">
        <div class="modal-header border-0 px-4 pt-4 pb-2">
          <div class="d-flex align-items-center gap-3">
            <span class="avatar-circle avatar-circle-lg" id="modalProfileAvatar">AS</span>
            <div>
              <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0" id="modalProfileName">Aanya Sen</h5>
                <span class="rating-badge d-none" id="modalProfileRating">★ 5.0</span>
              </div>
              <div class="text-muted small" id="modalProfileMeta">Weekends, online</div>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body px-4 py-3">
          <p class="text-secondary small mb-3" id="modalProfileBio"></p>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="ss-section-label mb-2">Teaches</div>
              <div class="d-flex flex-wrap gap-2" id="modalProfileTeach"></div>
            </div>
            <div class="col-md-6">
              <div class="ss-section-label mb-2">Wants to learn</div>
              <div class="d-flex flex-wrap gap-2" id="modalProfileLearn"></div>
            </div>
          </div>

          <div class="border-top pt-3" style="border-color: var(--ss-border) !important;">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="ss-section-label">Reviews</div>
              <span class="text-muted small" id="modalProfileReviewCount"></span>
            </div>
            <div id="modalProfileReviews" class="d-flex flex-column gap-2" style="max-height: 190px; overflow-y: auto;"></div>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-between flex-wrap gap-2">
          <button type="button" class="btn btn-ss-outline" id="modalOpenReviewBtn">Leave review</button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-ss-outline" id="modalRequestSwapBtn">Request swap</button>
            <a href="chats.php" class="btn btn-ss-primary" id="modalProfileChatBtn">Message</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Send Exchange Request Modal -->
  <div class="modal fade" id="exchangeRequestModal" tabindex="-1" aria-labelledby="exchangeRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-4 shadow">
        <div class="modal-header border-0 px-4 pt-4 pb-1">
          <div>
            <h5 class="modal-title fw-bold" id="exchangeRequestModalLabel">Send exchange request</h5>
            <p class="text-muted small mb-0" id="exchangeRequestSubtitle">Propose a 1-on-1 skill swap</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="exchangeRequestForm">
          <input type="hidden" name="partner_id" id="reqPartnerId" value="">
          <div class="modal-body px-4 py-3">
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label for="reqLearnSkill" class="form-label small fw-semibold">Skill I want to learn</label>
                <input type="text" class="form-control ss-input" id="reqLearnSkill" name="learn_skill" placeholder="e.g., Python" required>
              </div>
              <div class="col-md-6">
                <label for="reqTeachSkill" class="form-label small fw-semibold">Skill I'll teach</label>
                <input type="text" class="form-control ss-input" id="reqTeachSkill" name="teach_skill" placeholder="e.g., Guitar" required>
              </div>
            </div>
            <div class="mb-3">
              <label for="reqSchedule" class="form-label small fw-semibold">Preferred day, time &amp; format</label>
              <input type="text" class="form-control ss-input" id="reqSchedule" name="schedule_text" value="Saturday, 4:00 PM, video call" required>
            </div>
            <div class="mb-1">
              <label for="reqNote" class="form-label small fw-semibold">Intro message (optional)</label>
              <textarea class="form-control ss-input" id="reqNote" name="note" rows="2" placeholder="Share what you'd like to cover in the first hour..."></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 px-4 pb-4 pt-1">
            <button type="button" class="btn btn-ss-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ss-primary">Send request</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Leave Review Modal -->
  <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-4 shadow">
        <div class="modal-header border-0 px-4 pt-4 pb-1">
          <div>
            <h5 class="modal-title fw-bold" id="reviewModalLabel">Leave a review</h5>
            <p class="text-muted small mb-0" id="reviewModalSubtitle">Rate your skill exchange experience</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="reviewForm">
          <input type="hidden" name="reviewee_id" id="revRevieweeId" value="">
          <input type="hidden" name="proposal_id" id="revProposalId" value="">
          <div class="modal-body px-4 py-3">
            <div class="mb-3">
              <label class="form-label small fw-semibold d-block">Rating</label>
              <div class="btn-group w-100" role="group" aria-label="Star rating">
                <?php for ($r = 1; $r <= 5; $r++): ?>
                  <input type="radio" class="btn-check" name="rating" id="star<?= $r ?>" value="<?= $r ?>" <?= $r === 5 ? 'checked' : '' ?>>
                  <label class="btn btn-outline-secondary" for="star<?= $r ?>"><?= $r ?> ★</label>
                <?php endfor; ?>
              </div>
            </div>
            <div class="mb-3">
              <label for="revSkill" class="form-label small fw-semibold">Skill practiced</label>
              <input type="text" class="form-control ss-input" id="revSkill" name="skill_practiced" placeholder="e.g., Python">
            </div>
            <div class="mb-1">
              <label for="revComment" class="form-label small fw-semibold">Review comment</label>
              <textarea class="form-control ss-input" id="revComment" name="comment" rows="3" placeholder="How was the session?" required></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 px-4 pb-4 pt-1">
            <button type="button" class="btn btn-ss-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-ss-primary">Submit review</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Toast feedback -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="ssToast" class="toast align-items-center text-bg-dark border-0 rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body small" id="ssToastBody">Saved!</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="assets/js/app.js"></script>
  <?php if (!empty($extraScripts)): ?>
    <?php foreach ($extraScripts as $scriptPath): ?>
      <script src="<?= e($scriptPath) ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
