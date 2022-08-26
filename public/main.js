const { createApp, ref } = Vue;

const STATES = {
  Initial: "initial",
  Requesting: "requesting",
  Error: "error",
  Success: "success",
};

const app = createApp({
  setup() {
    const teams = ref("");
    const numberFields = ref(1);
    const matchTime = ref(15);
    const startTime = ref("10:00");
    const endTime = ref("16:00");
    const state = ref(STATES.Initial);
    const result = ref(null);
    const totalGames = ref(0);
    const groupsByField = ref([]);

    const generate = () => {
      state.value = STATES.Requesting;
      result.value = null;
      totalGames.value = 0;

      const formData = new FormData();
      formData.append("teams", teams.value);
      formData.append("numberFields", numberFields.value);
      formData.append("matchTime", matchTime.value);
      formData.append("startTime", startTime.value);
      formData.append("endTime", endTime.value);

      fetch("/api.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => {
          return new Promise((resolve) =>
            response.json().then((json) =>
              resolve({
                status: response.status,
                ok: response.ok,
                json,
              })
            )
          );
        })
        .then(({ json, ok }) => {
          if (!ok) {
            state.value = STATES.Error;
            result.value = json.message;

            return;
          }

          state.value = STATES.Success;
          result.value = json;

          totalGames.value = json.fields
            .map((f) => f.length)
            .reduce((acc, c) => c + acc, 0);
          groupsByField.value = json.fields.map((f) =>
            Array.from(new Set(f.map((g) => g.group))).sort((a, b) => a - b)
          );
        })
        .catch(() => {
          state.value = STATES.Error;
          result.value = "Der skete en fejl";
        });
    };

    return {
      STATES,

      teams,
      numberFields,
      matchTime,
      startTime,
      endTime,
      generate,

      state,
      result,
      totalGames,
      groupsByField,
    };
  },
});

document.addEventListener("DOMContentLoaded", function (event) {
  app.mount("#app");
});
